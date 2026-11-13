# omnisense/omnipost

The social networks' family, with two contracts: **publish** - a post (a reel, a picture, a
carousel) published through a network's API, and one's own feed read back - and **share** - plain
share links for a page's visitors, with no network script and no cookie. The Omnibus of
publication, beside glitchr/omnitrade (payments) and glitchr/omnibus (shipping).

`omnisense/omnishare` was merged into this package on 2026-11-09, with its history: its share
links are `Omnipost\Share\` ([Share links](#share-links)).

Published as `omnisense/omnipost` (formerly `glitchr/omnipost`, which this package replaces: a
`composer.json` that still requires the old name keeps resolving to this package, never both).
`omnisense/` is the vendor of every family's core; the providers keep theirs (`omnipost/instagram`...).

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

This package holds the publishing contract (`PublisherInterface`, `FeedInterface`,
`ProviderFactory`, `Registry`, `Validator`), the models (`Post`, `Media`, `Variant`, `Publication`,
`Feed`, `FeedItem`, `Account`, `Token`, `Capabilities`, `Violation`), the share links
(`Share\NetworkInterface`, `Share\Registry`, `Share\Shareable`, `Share\Share`, `Share\Link` and
the networks) and a bridge for Symfony. It needs no framework: it requires nothing but
`symfony/http-client-contracts`, each provider package `symfony/http-client`. Each network that is
published to is a package of its own:

| Package | Network |
|---|---|
| `omnipost/instagram` | Instagram: the feed, pictures, carousels, reels and stories, the 60-day token's refresh (Instagram API with Instagram Login) |
| `omnipost/youtube` | YouTube: the channel's videos with a key, videos and Shorts uploaded through OAuth (Data API v3) |

`DistributorInterface` is reserved - delivering a release to the streaming platforms through a
distributor - and has no implementation yet.

## The contracts, and who implements them

| Package | Contracts |
|---|---|
| `omnisense/omnipost` (this core) | publish: `PublisherInterface`, `FeedInterface`, `Auth\RefreshableInterface`, `Auth\OAuthInterface` (declared); share: `Share\NetworkInterface` and every network that has a share page, implemented here (`Share\Registry::defaults()`) |
| `omnipost/instagram` | publish, feed, token refresh, OAuth. No share: Instagram has no share page on the web |
| `omnipost/youtube` | publish, feed, token refresh, OAuth. No share: YouTube has no share page on the web |

A share network is an address template - no dependency, no option, no credential, no API - so the
thirteen are in the core's share table rather than in thirteen provider packages of thirty lines
each; a provider package is for what needs a network's API. Facebook, X, Threads, LinkedIn,
Bluesky, Mastodon and Pinterest have a share link here and a publishing API no provider calls yet;
WhatsApp, Telegram, Reddit, the e-mail, the copied link and the device's share sheet are share
only; Instagram, YouTube and TikTok are publish only. The table per network:
[docs/index.md](docs/index.md#the-contracts-per-network).

## Install and a first call

```sh
composer require omnisense/omnipost
```

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnipost\Share\Registry;
use Omnipost\Share\Shareable;

$share = Registry::defaults()->share(new Shareable('https://example.com/articles/fuji', 'Mount Fuji at dawn'), ['x', 'email']);
echo $share->url('x'), "\n";   // https://twitter.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&text=Mount%20Fuji%20at%20dawn
```

The share links need nothing: no account, no key, no request (see [Share links](#share-links)).
Publishing and reading a feed go through a network's API, and **every network needs an account for
a real call**: Instagram a professional account and a Meta app (its access token; the account added
as an Instagram Tester while the app is in development mode), YouTube an API key for a channel's
public videos and an OAuth client (Google Cloud) to upload. The first call of a provider, on
YouTube's recorded answer, so that it runs without a key and nothing leaves the process:

```sh
composer require omnisense/omnipost omnipost/youtube
```

```php
use Omnipost\Registry;
use Omnipost\YouTube\YouTubeProviderFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$http = new MockHttpClient(new MockResponse(json_encode(['items' => [['id' => 'UCabc', 'snippet' => ['title' => 'Claire', 'customUrl' => '@claireviolin'],
    'statistics' => ['subscriberCount' => '4200', 'videoCount' => '36'], 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUabc']]]]]),
    ['response_headers' => ['Content-Type' => 'application/json']]));
$registry = new Registry([new YouTubeProviderFactory($http)], ['youtube' => ['factory' => 'youtube', 'options' => ['api_key' => 'not-a-key', 'channel_id' => 'UCabc']]]);
$account = $registry->get('youtube')->account();
echo $account->username, ', ', $account->followers, " subscribers\n";   // @claireviolin, 4200 subscribers
```

With `HttpClient::create()` (or the application's client), a key and a channel's id, the same lines
read the real channel.

## Plain PHP

```sh
composer require omnisense/omnipost omnipost/instagram omnipost/youtube
```

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
and makes its own when given none. A whole script that runs as it is, on recorded answers, and the
rest: [docs/installation.md](docs/installation.md). No class of a framework is loaded on the way:
`Tests/BareTest.php` checks it in a process of its own, and so does
`docker compose run --rm omnipost bare`.

## Share links

```php
use Omnipost\Share\Registry;
use Omnipost\Share\Shareable;

$share = Registry::defaults()->share(
    new Shareable('https://example.com/articles/fuji', 'Mount Fuji at dawn', image: 'https://example.com/fuji.jpg', hashtags: ['japan']),
    ['facebook', 'x', 'pinterest', 'email', 'native'],   // the ones the page shows, in its order
);
$share->url('x');     // https://twitter.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&text=Mount%20Fuji%20at%20dawn&hashtags=japan
$share->toArray();    // the one JSON Twig, Blade and React read: the page, then each link's url, label, icon, colour, newTab, action
```

A page's address, title, text, picture and hashtags turned into each network's own share page
(Facebook, X, Pinterest, LinkedIn, Bluesky, Mastodon, Threads, WhatsApp, Telegram, Reddit), an
e-mail, a copied link and the device's share sheet. **Plain links**: no network's script is
loaded, so no cookie is set and nothing needs the visitor's consent; no account, no credential, no
API call. `Omnipost\Share` needs PHP alone - no HTTP client, no framework: `Tests/Share/BareTest`
checks it loads no class outside itself. `assets/omnipost-share.js` (no dependency) adds the share
sheet, the copy and the visitor's Mastodon instance where the browser can; labels in English and
French are in `Share/translations/`.

From `omnisense/omnishare`: `Omnishare\` is `Omnipost\Share\` (its exceptions
`Omnipost\Exception\`), the label keys `omnishare.*` are `omnipost.share.*`, and the script is
`assets/omnipost-share.js` (`data-omnipost-share`, `OmnipostShare`, `omnipost-share:copied`).
`omnisense/omnishare` is not replaced by this package: its namespace changed, so an application
changes its `use` lines and requires `omnisense/omnipost`. The manual: [docs/share.md](docs/share.md).

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
docker compose run --rm omnipost bare-share                          # plain PHP: every share link of a page, and what PHP loaded
docker compose run --rm omnipost test                                # every package's tests
docker compose run --rm js                                           # the share script's tests (node)
```

`validate` and `publish` measure what they can (the type and size the server announces, a local
`--file`); the duration and the size in pixels are given with `--duration`, `--width`, `--height`.

## Documentation

- [The manual's home](docs/index.md): what it is, the providers
- [Installation and first calls](docs/installation.md): plain PHP first
- [Symfony](docs/symfony.md), [Laravel](docs/laravel.md)
- [Concepts](docs/concepts.md): providers and their interfaces, the registry, posts, media, publications, tokens
- [Share links](docs/share.md): [concepts](docs/share-concepts.md), [the networks and how each address was checked](docs/share-networks.md), [templates](docs/share-templates.md), [the script](docs/share-javascript.md)
- [Publishing](docs/usage-publishing.md), [Validating a post](docs/usage-validation.md), [Reading an account](docs/usage-reading.md), [Tokens and connecting an account](docs/usage-tokens.md)
- [Configuration reference](docs/configuration.md), [Errors](docs/errors.md), [Testing](docs/testing.md)
- [The Docker harness](docs/harness.md), [Writing a provider](docs/providers.md)
- [Verified, and not](docs/verified.md)

## Responsibility

`omnisense/omnipost` and its `omnipost/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the services it reaches. Each service keeps its own
terms (app review and permissions, tokens, quotas, content and branding rules), named with a link in
the "Responsibility" section of the README of each provider that reaches one. The MIT licence covers
this code only and grants no right to any service or to its data.

Whoever installs and runs these packages is responsible for complying with those terms, for
obtaining whatever they require, and for any use made of what they return. The authors publish them
free of charge, make no commercial use of them, and accept no liability for how they are used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
