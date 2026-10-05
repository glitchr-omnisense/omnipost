# glitchr/omnipost

One contract for publishing a post on the social networks and reading one's own feed - the
Omnibus of publication, beside glitchr/omnitrade (payments) and glitchr/omnibus (shipping).

```php
$post = Post::reel(Media::video($publicUrl, $path, 42.0, 1080, 1920), 'Strauss, tonight.', null, ['violin'])
    ->withVariant(new Variant(Platform::YOUTUBE, title: 'Strauss, Sonata'));

$violations = $validator->validate($post->for(Platform::INSTAGRAM), $instagram->capabilities());   // [] or what would be refused
$publication = $instagram->publish($post->for(Platform::INSTAGRAM));   // PENDING: Instagram fetches and transcodes
while (!$publication->state->isFinal()) {
    sleep(5);
    $publication = $instagram->status($publication->id);              // PROCESSING, then PUBLISHED with its permalink
}

$youtube->publish($post->for(Platform::YOUTUBE));                      // uploaded, private until the artist opens it
$instagram->feed()->items;                                             // the account's posts, for a wall on the site
```

**One canonical post, its variants per network.** A site renders one reel - a vertical video,
the one thing every network takes - with one caption and its hashtags; a `Variant` gives a
network what it wants instead (YouTube's title, a shorter caption, other hashtags, another
rendering, a provider's option such as YouTube's `privacy`). `$post->for(Platform::X)` is the post
as that network receives it, and `Post::text()` the caption with the hashtags appended.

**Checked before it is sent.** Every publisher says what it accepts (`capabilities()`: the
kinds, the durations, the ratios, the sizes, the caption's length, the hashtags, the title);
the `Validator` lists the violations, readable, by field, for a back office to show; `publish()`
runs it first and sends nothing when the post would be refused (`InvalidPostException`).

**The tokens stay with the site.** A provider is built from options - a token, an app's id - and
calls the network directly, through the application's HTTP client. `Token` says when it dies;
`refresh()` (`RefreshableInterface`) gives a fresh one for the site to store, `authorizationUrl()`
and `exchange()` (`OAuthInterface`) connect the account from a back office.

Hosted services do this already - Ayrshare (an API in front of the networks, the accounts
connected on their side, a subscription per profile), Postiz (a self-hosted scheduling
application, with its own interface and database). Omnipost is neither: a library inside the
site, no third party holding the artist's tokens, no second application to run, nothing to pay
but the networks' own terms.

This package holds the contract (`PublisherInterface`, `FeedInterface`, `ProviderFactory`,
`Registry`, `Validator`), the models (`Post`, `Media`, `Variant`, `Publication`, `Feed`,
`FeedItem`, `Account`, `Token`, `Capabilities`, `Violation`) and a bridge for Symfony. It needs no
framework: it requires nothing but `symfony/http-client-contracts`, each provider package
`symfony/http-client`. Each network is a package of its own:

| Package | Network |
|---|---|
| `omnipost/instagram` | Instagram: the feed, pictures, carousels, reels and stories, the 60-day token's refresh (Instagram API with Instagram Login) |
| `omnipost/youtube` | YouTube: the channel's videos with a key, videos and Shorts uploaded through OAuth (Data API v3) |

`DistributorInterface` is reserved - delivering a release to the streaming platforms through a
distributor - and has no implementation yet.

## Install

```sh
composer require glitchr/omnipost omnipost/instagram omnipost/youtube
```

## Plain PHP

```php
require __DIR__.'/vendor/autoload.php';

use Omnipost\Instagram\InstagramProviderFactory;
use Omnipost\Registry;
use Omnipost\Validator;
use Omnipost\YouTube\YouTubeProviderFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$registry = new Registry([new InstagramProviderFactory($http), new YouTubeProviderFactory($http)], [
    'instagram' => ['factory' => 'instagram', 'options' => ['access_token' => '...']],
    'youtube' => ['factory' => 'youtube', 'options' => ['api_key' => '...', 'channel_id' => 'UC...']],
]);
$registry->publishers();   // the providers that publish
$registry->feeds();        // the ones that have a feed

foreach ($registry->publishers() as $publisher) {
    (new Validator())->validate($post->for($publisher->getPlatform()), $publisher->capabilities());   // [] or what would be refused
}
```

A factory takes the HTTP client to call with - the application's, a `MockHttpClient` in a test -
and makes its own when given none. A whole script that runs as it is, and the rest:
[docs/installation.md](docs/installation.md). No class of a framework is loaded on the way:
`Tests/BareTest.php` checks it in a process of its own, and so does
`docker compose run --rm omnipost bare`.

## Symfony

In a Symfony application a bundle does the wiring; its components (`symfony/config`,
`symfony/dependency-injection`, `symfony/http-kernel`) are not required by this package: the
application has them ([docs/symfony.md](docs/symfony.md)).
`Omnipost\Bridge\Symfony\OmnipostBundle`: every `omnipost/*` provider installed registered,
`Omnipost\Registry` and `Omnipost\Validator` autowired, and each configured provider injectable
by its name.

```yaml
omnipost:
    providers:
        instagram: { factory: instagram, options: { access_token: '%env(default::INSTAGRAM_ACCESS_TOKEN)%', app_id: '%env(default::INSTAGRAM_APP_ID)%', app_secret: '%env(default::INSTAGRAM_APP_SECRET)%' } }
        youtube:   { factory: youtube, options: { api_key: '%env(default::YOUTUBE_API_KEY)%', channel_id: '%env(default::YOUTUBE_CHANNEL_ID)%' } }
```

```php
public function __construct(PublisherInterface $instagram, FeedInterface $youtube, Registry $omnipost, Validator $validator) {}
```

Nothing is built when the container compiles: a provider is built the first time it is asked
for, and a credential left empty (`%env(default::...)%` with the variable unset) only shows when a
call needs it, as an `InvalidConfigException` - `capabilities()` and `authorizationUrl()` need no
token. So a site boots before its accounts are connected; to know whether one is,
`$registry->has('instagram')` (declared) and a call inside `try { } catch (InvalidConfigException)`
(configured). A token kept in a database rather than in the environment:
`$registry->create('instagram', ['access_token' => $stored])`.

An application's own `ProviderFactoryInterface` is registered too (autoconfigured).

## Per network, what it takes

- **Instagram**: a professional (Business or Creator) account, a Meta app with the "Instagram API
  with Instagram Login" product, the account added as an Instagram Tester while the app is in
  development (no App Review for a site that posts on its own account), a long-lived token
  refreshed before its 60 days are out, and the media at a public URL Meta can fetch. See
  `omnipost/instagram`.
- **YouTube**: an API key and the channel's id to read; to upload, an OAuth client and the
  channel's consent (a refresh token). Until the Google Cloud project passes Google's audit,
  uploads are locked private: the artist publishes them from YouTube Studio. See `omnipost/youtube`.

## Docker: every provider with your tokens

`docker/` runs this package with every `omnipost/*` provider installed - from GitHub, or from the
checkouts beside this one when `OMNIPOST_PLUGINS=../..` is set - and a console that exercises
them with the tokens in `docker/.env` (copy `.env.dist`; `providers` says which providers are
configured, and for what):

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnipost providers
docker compose run --rm omnipost capabilities instagram              # no token needed
docker compose run --rm omnipost validate instagram https://site.example/reel.mp4 "Strauss" --duration 42 --width 1080 --height 1920 --tag violin
docker compose run --rm omnipost account instagram
docker compose run --rm omnipost feed youtube --limit 5
docker compose run --rm omnipost publish instagram https://site.example/reel.mp4 "Strauss" --tag violin   # followed until published
docker compose run --rm omnipost publish youtube https://site.example/reel.mp4 "Strauss" --title "Strauss, Sonata" -o privacy=unlisted
docker compose run --rm omnipost status instagram 17890...
docker compose run --rm omnipost refresh instagram                   # the new token, as JSON
docker compose run --rm omnipost authorize youtube http://localhost/callback
docker compose run --rm omnipost exchange youtube 4/0A... http://localhost/callback   # prints the refresh token
docker compose run --rm omnipost bare                                # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omnipost test                                # every package's tests
```

`validate` and `publish` measure what they can (the type and size the server announces, a local
`--file`); the duration and the size in pixels are given with `--duration`, `--width`, `--height`.

## Documentation

- [Installation and first calls](docs/installation.md): plain PHP first
- [Symfony](docs/symfony.md)
- [The Docker harness](docs/harness.md)

License: LGPL-3.0-or-later.
