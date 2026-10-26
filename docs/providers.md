---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a class that implements `Omnipost\ProviderInterface` and what it does among
`PublisherInterface`, `FeedInterface`, `Auth\RefreshableInterface` and `Auth\OAuthInterface`,
built by a factory that extends `Omnipost\ProviderFactory`. It can live in the application or be a
package of its own, as `omnipost/instagram` and `omnipost/youtube` are.

## The rules

- **Refuse before sending.** `publish()` validates the post against `capabilities()` (the core's
  `Validator`, plus the rules that depend on the kind) and throws `InvalidPostException` with every
  violation, before any request.
- **Say what the network accepts.** `capabilities()` is what a back office shows and checks
  against: kinds, durations, ratios, sizes, formats, caption and title lengths, hashtags, whether
  it schedules. It needs no credential.
- **A state, not a promise.** `publish()` answers with the network's id and the state it is in;
  `status($id)` reads it again until `isFinal()`. When the id changes on the way, the answer
  carries the new one.
- **Nothing required to build.** Credentials are read when a call needs them, and their absence is
  an `InvalidConfigException` naming them: a site boots before its accounts are connected.
- **The tokens go back to the caller.** `refresh()` and `exchange()` answer a `Token` with its end;
  the provider uses the new one from then on, and stores nothing.
- **Errors.** A dead or refused token is `UnauthorizedException`; any other refusal or a network
  that cannot be reached a `ProviderException` with the HTTP status and the network's code; what the
  network's API cannot do (`delete()` on Instagram) `NotSupportedException`. Keep tokens out of
  messages.
- **Read the variant's post.** A provider is given `$post->for($platform)`; it reads the post's
  fields and `options`, never `variants`.

## A provider of the application's

A sketch of Mastodon, which publishes a status with the picture's link (no media upload):

```php
<?php // mastodon.php

require __DIR__.'/vendor/autoload.php';

use Omnipost\Config;
use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\InvalidPostException;
use Omnipost\Exception\ProviderException;
use Omnipost\Exception\UnauthorizedException;
use Omnipost\Model\Capabilities;
use Omnipost\Model\Media;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Publication;
use Omnipost\Model\PublicationState;
use Omnipost\Platform;
use Omnipost\ProviderFactory;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;
use Omnipost\Registry;
use Omnipost\Validator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** A status on a Mastodon instance, its picture linked (a sketch: no media upload). */
final class MastodonProvider implements PublisherInterface
{
    public function __construct(private readonly HttpClientInterface $http, private readonly string $instance, private readonly ?string $token)
    {
    }

    public function getName(): string { return 'mastodon'; }

    public function getPlatform(): Platform { return Platform::MASTODON; }

    public function capabilities(): Capabilities
    {
        return new Capabilities(kinds: [PostKind::IMAGE, PostKind::REEL], captionMaxLength: 500, mediaMaxCount: 1);
    }

    public function publish(Post $post): Publication
    {
        if ($violations = (new Validator())->validate($post, $this->capabilities())) {
            throw new InvalidPostException($this->getName(), $violations);    // nothing sent
        }
        $status = $this->call('POST', '/api/v1/statuses', ['status' => $post->text()."\n".$post->media[0]->url]);

        return new Publication(Platform::MASTODON, (string) $status['id'], PublicationState::PUBLISHED, $status['url'] ?? null, raw: $status);
    }

    public function status(string $publicationId): Publication
    {
        $status = $this->call('GET', '/api/v1/statuses/'.$publicationId);

        return new Publication(Platform::MASTODON, $publicationId, PublicationState::PUBLISHED, $status['url'] ?? null, raw: $status);
    }

    public function delete(string $publicationId): void
    {
        $this->call('DELETE', '/api/v1/statuses/'.$publicationId);
    }

    private function call(string $method, string $path, ?array $json = null): array
    {
        $token = $this->token ?? throw new InvalidConfigException('The "mastodon" provider needs: token.');
        $response = $this->http->request($method, rtrim($this->instance, '/').$path, ['auth_bearer' => $token] + (null !== $json ? ['json' => $json] : []));
        $status = $response->getStatusCode();
        $data = json_decode($response->getContent(false), true) ?: [];
        if (401 === $status) {
            throw new UnauthorizedException($this->getName(), $data['error'] ?? 'HTTP 401', 401);
        }
        if ($status >= 400) {
            throw new ProviderException($this->getName(), $data['error'] ?? 'HTTP '.$status, $status);
        }

        return $data;
    }
}

final class MastodonProviderFactory extends ProviderFactory
{
    protected function populate(Config $config): void
    {
        $config->defaults(['omnipost.factory_name' => 'mastodon', 'omnipost.required_options' => ['instance'], 'token' => null]);
    }

    protected function build(Config $config): ProviderInterface
    {
        return new MastodonProvider($this->http ?? HttpClient::create(), (string) $config['instance'], $config['token']);
    }
}

$http = new MockHttpClient(fn (string $method, string $url) => new MockResponse(json_encode(['id' => '1131', 'url' => 'https://mastodon.example/@claire/1131'])));
$registry = new Registry([new MastodonProviderFactory($http)], [
    'fediverse' => ['factory' => 'mastodon', 'options' => ['instance' => 'https://mastodon.example', 'token' => getenv('MASTODON_TOKEN') ?: 'test']],
]);
$publication = $registry->get('fediverse')->publish(Post::image(Media::image('https://site.example/poster.jpg'), 'Tonight at 8.', ['violin']));
echo $publication->state->value, ' ', $publication->permalink, "\n";
try {
    $registry->get('fediverse')->publish(new Post(PostKind::CAROUSEL, []));
} catch (InvalidPostException $e) {
    echo $e->getMessage(), "\n";
}
try {
    (new MastodonProviderFactory())->create(['instance' => '']);
} catch (InvalidConfigException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php mastodon.php
published https://mastodon.example/@claire/1131
[mastodon] kind: carousel posts are not accepted (accepted: image, reel).; media: No media.
The "mastodon" provider needs: instance.
```

(run on 2026-10-26 on a `MockHttpClient`, PHP 8.4; no Mastodon instance was called.) The factory's
`instance` is required: it has no sensible default, and an empty one is refused by `create()`;
the token is not, so `capabilities()` works without it.

In Symfony, the factory is autoconfigured (tagged `omnipost.provider_factory`) as soon as it is a
service, and named in `omnipost.providers.<name>.factory`
([Symfony](symfony.md#an-applications-own-provider)).

## The factory

`populate(Config $config)` sets, with `$config->defaults([...])`:

| Key | |
|---|---|
| `omnipost.factory_name` | the factory's name, what `factory:` names |
| `omnipost.required_options` | the options `create()` refuses to go without - keep it empty for credentials |
| every other option | its default |

Options given as `null` or `''` never reach `populate()`: the default stands. `build(Config $config)`
makes the provider from the filled config, on `$this->http` (the client the factory was given) or
its own (`HttpClient::create()`, which needs `symfony/http-client`). Nothing is called while
building.

## A package of its own

The shipped providers' layout, to copy:

- name `omnipost/<slug>`, namespace `Omnipost\<Network>\`, PSR-4 from the package root, `Tests/`
  excluded from the class map;
- `require`: `php` `>=8.2`, `omnisense/omnipost` `^1.0@dev`, `symfony/http-client`
  (`^6.4|^7.0|^8.0`);
- an `Api` class that holds the token, adds it to each call, and maps the network's errors; the
  provider class on top, with `validate()` public;
- the endpoints as constants (`BASE_URI`, `VERSION`), overridable by options;
- tests on answers in the shapes the network documents, through a `MockHttpClient`;
- a README with what it takes on the network's side (accounts, app review, scopes) and a
  "Responsibility" section naming the network's terms.

For the bundle to register the factory without the application declaring it, its class must be in
`OmnipostBundle::FACTORIES`; for the harness to install it, in `docker/harness/plugins.php` and
`docker/harness/config/providers.php`.
