# Installation and first calls

```sh
composer require glitchr/omnipost omnipost/instagram omnipost/youtube
```

PHP 8.2 or later.

Omnipost needs no framework. The core requires nothing but `symfony/http-client-contracts`, each
provider package `symfony/http-client`: two libraries that stand alone. It runs the same in plain
PHP, in a worker, in Laravel or Slim, and in Symfony, where a bundle does the wiring
([Symfony](symfony.md)).

## Plain PHP

```php
<?php // bare.php

require __DIR__.'/vendor/autoload.php';

use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Model\Media;
use Omnipost\Model\Post;
use Omnipost\Model\Variant;
use Omnipost\Platform;
use Omnipost\Registry;
use Omnipost\Validator;
use Omnipost\YouTube\YouTubeProviderFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// The HTTP client the providers call with: in an application, HttpClient::create(). Here, one that
// answers as YouTube's Data API does, so that the script runs as it is, without a key.
$http = new MockHttpClient(static fn (string $method, string $url) => new MockResponse(json_encode(match (parse_url($url, PHP_URL_PATH)) {
    '/youtube/v3/channels' => ['items' => [['id' => 'UCabc', 'snippet' => ['title' => 'Claire', 'customUrl' => '@claireviolin'], 'statistics' => ['subscriberCount' => '4200', 'videoCount' => '36'], 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUabc']]]]],
    '/youtube/v3/playlistItems' => ['items' => [['snippet' => ['title' => 'Strauss, Sonata', 'resourceId' => ['videoId' => 'shortA']], 'contentDetails' => ['videoId' => 'shortA', 'videoPublishedAt' => '2026-09-30T18:00:00Z']]]],
    '/youtube/v3/videos' => ['items' => [['id' => 'shortA', 'contentDetails' => ['duration' => 'PT58S'], 'statistics' => ['viewCount' => '1200']]]],
}), ['response_headers' => ['Content-Type' => 'application/json']]));

$registry = new Registry([new InstagramProviderFactory($http), new YouTubeProviderFactory($http)], [
    'instagram' => ['factory' => 'instagram'],   // options: access_token, app_id, app_secret - none needed to check a post
    'youtube' => ['factory' => 'youtube', 'options' => ['api_key' => 'youtube_test_not_a_real_key', 'channel_id' => 'UCabc']],
]);

// One canonical post, checked against what each network accepts before anything is sent
$validator = new Validator();
$reel = Post::reel(Media::video('https://site.example/reel.mp4', null, 42.0, 1080, 1920), 'Strauss, tonight.', null, ['violin']);
foreach ([$reel, $reel->withVariant(new Variant(Platform::YOUTUBE, title: 'Strauss, Sonata'))] as $post) {
    foreach ($registry->publishers() as $publisher) {
        $violations = $validator->validate($post->for($publisher->getPlatform()), $publisher->capabilities());
        printf("%-10s %s\n", $publisher->getName(), $violations ? implode(' ', $violations) : 'accepted');
    }
}

// The channel's latest videos, for a wall on the site
$youtube = $registry->get('youtube');
echo "\n", $youtube->account()->username, ', ', $youtube->account()->followers, " subscribers\n";
foreach ($youtube->feed(null, 5)->items as $item) {
    echo $item->publishedAt->format('Y-m-d'), ' ', $item->kind->value, ' ', $item->caption, ' (', $item->duration, ' s, ', $item->views, ' views) ', $item->permalink, "\n";
}
```

```
$ php bare.php
instagram  accepted
youtube    title: A title is required.
instagram  accepted
youtube    accepted

@claireviolin, 4200 subscribers
2026-09-30 reel Strauss, Sonata (58 s, 1200 views) https://www.youtube.com/watch?v=shortA
```

(run on 2026-10-05 in an empty directory, after the `composer require` above; the channel and its
video are made up - every network asks for an account's key or token, so the script answers in
YouTube's place. With `HttpClient::create()`, your key and your channel's id, the same lines read
the real channel.)

That is all there is to it:

- a **factory** per provider package (`InstagramProviderFactory`, `YouTubeProviderFactory`),
  which takes the HTTP client to call with - the application's, a `MockHttpClient` in a test;
  with none given it makes its own (`HttpClient::create()`);
- the **registry**, built by hand from the factories and the providers' options, by name:
  `get()`, `publishers()`, `feeds()`, and `create($name, ['access_token' => $stored])` for a token
  kept in a database;
- the **validator**, which lists what a network would refuse before anything is sent;
- the **providers** it gives: `publish()`, `status()`, `delete()`, `capabilities()`
  (`PublisherInterface`), `account()`, `feed()` (`FeedInterface`), `refresh()`
  (`Auth\RefreshableInterface`), `authorizationUrl()` and `exchange()` (`Auth\OAuthInterface`).

No class of a framework is loaded on the way - a test of this package checks it in a process of
its own (`Tests/BareTest.php`), and so does `docker compose run --rm omnipost bare`
([harness](harness.md)).

## Publishing

```php
use Omnipost\Exception\InvalidPostException;
use Omnipost\Instagram\InstagramProviderFactory;
use Symfony\Component\HttpClient\HttpClient;

$instagram = (new InstagramProviderFactory(HttpClient::create()))->create(['access_token' => getenv('INSTAGRAM_ACCESS_TOKEN')]);

try {
    $publication = $instagram->publish($post->for(Platform::INSTAGRAM));   // PENDING: Instagram fetches and transcodes
} catch (InvalidPostException $e) {
    $e->violations;                                                        // refused before anything was sent
}
while (!$publication->state->isFinal()) {
    sleep(5);
    $publication = $instagram->status($publication->id);                  // PROCESSING, then PUBLISHED with its permalink
}
```

The media must be at a public URL the network can fetch (Instagram), or a local file the provider
uploads (YouTube: `Media::video($url, $path, ...)`).

## The tokens stay with the site

```php
$token = $instagram->refresh();              // a fresh long-lived token: store $token->toArray()
$url = $youtube->authorizationUrl('https://site.example/callback', $state);
$token = $youtube->exchange($_GET['code'], 'https://site.example/callback');   // the refresh token to keep
```

`Token` says when it dies. A provider whose credential is missing still builds: `capabilities()`
and `authorizationUrl()` need none, and a call that needs one throws `InvalidConfigException`.

## In a framework

- **Symfony**: `Omnipost\Bridge\Symfony\OmnipostBundle` registers the factories on the
  application's `http_client`, builds the registry from `config/packages/omnipost.yaml` and makes
  each provider injectable by its name: see [Symfony](symfony.md). Its components
  (`symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`) are not required by
  this package: a Symfony application has them.
- **Any other**: build the `Registry` once, where the framework builds its services (a service
  provider, a container definition), as the script above does.

## Errors

| Exception | When |
|---|---|
| `InvalidPostException` | the post breaks the network's `capabilities()`: nothing was sent (`$violations`) |
| `InvalidConfigException` | a provider not configured, a factory not installed, a credential missing for the call |
| `UnauthorizedException` | the token is dead or refused: refresh it, or connect the account again |
| `NotSupportedException` | the network's API cannot do that (delete a post on Instagram) |
| `ProviderException` | any other refusal of the network |

All implement `Omnipost\Exception\OmnipostException`.
