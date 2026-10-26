---
title: Symfony
order: 15
---

# Symfony

Omnipost runs without a framework ([Installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` (6.4, 7 or 8) - are not required by `omnisense/omnipost`: the application has
them, and nothing of them is loaded outside Symfony. The bundle is part of the core
(`Omnipost\Bridge\Symfony\OmnipostBundle`); omnibridge has no Omnipost bridge, and none is needed.

## Registering the bundle

There is no Flex recipe: add the bundle by hand.

```php
// config/bundles.php
return [
    // ...
    Omnipost\Bridge\Symfony\OmnipostBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/omnipost.yaml
omnipost:
    providers:                     # by name: a factory and its options
        instagram: { factory: instagram, options: { access_token: '%env(default::INSTAGRAM_ACCESS_TOKEN)%', app_id: '%env(default::INSTAGRAM_APP_ID)%', app_secret: '%env(default::INSTAGRAM_APP_SECRET)%' } }
        youtube:   { factory: youtube, options: { api_key: '%env(default::YOUTUBE_API_KEY)%', channel_id: '%env(default::YOUTUBE_CHANNEL_ID)%' } }
```

The whole tree, from the bundle's `configure()`:

| Key | Type | Default | |
|---|---|---|---|
| `omnipost.providers` | map, keyed by the provider's name | `{}` | the site's networks |
| `omnipost.providers.<name>.factory` | string, required, not empty | - | a factory's name: `instagram`, `youtube`, or an application's own factory's `getName()` |
| `omnipost.providers.<name>.options` | any value (a map) | `[]` | handed as they are to the factory's `create()` ([Configuration](configuration.md)) |

Processed with nothing set, the tree is `{"providers": []}`; a provider without its factory is
refused when the container compiles (`The child config "factory" under "omnipost.providers.ig" must
be configured.`). The options are not validated then: no option of either shipped provider is
required to build it, and a credential left empty (`%env(default::...)%` with the variable unset)
is the same as not given - it shows only when a call needs it, as an `InvalidConfigException`.

## Services

Each provider package the bundle knows - `omnipost/instagram`
(`Omnipost\Instagram\InstagramProviderFactory`) and `omnipost/youtube`
(`Omnipost\YouTube\YouTubeProviderFactory`) - has its factory registered when its class exists,
built on the application's `http_client` service, or with none when the application has no such
service (the factory's API client then makes its own).

| Service id | Class | Autowiring | |
|---|---|---|---|
| `Omnipost\Registry` | `Registry` | `Registry $registry` | every configured provider by name. Public. |
| `Omnipost\Validator` | `Validator` | `Validator $validator` | a post against a network's capabilities |
| `omnipost.provider.<name>` | `ProviderInterface` | `ProviderInterface $<name>`, `PublisherInterface $<name>`, `FeedInterface $<name>` | one provider, built by `Registry::get('<name>')` the first time it is injected |
| the factories | their class | by their class | tagged `omnipost.provider_factory` |

```php
use Omnipost\FeedInterface;
use Omnipost\PublisherInterface;
use Omnipost\Registry;
use Omnipost\Validator;

final class SocialController
{
    public function __construct(
        private readonly PublisherInterface $instagram,   // the "instagram" provider, as a publisher
        private readonly FeedInterface $youtube,          // the "youtube" provider, as a feed
        private readonly Registry $registry,
        private readonly Validator $validator,
    ) {
    }
}
```

The argument's name selects the provider; the type says what it is used for. Each provider is
aliased under all three types whatever it implements: a provider that is not a publisher, injected
as `PublisherInterface`, fails at injection with a type error. `Auth\OAuthInterface` and
`Auth\RefreshableInterface` have no alias: inject the provider as one of the three types and check
`instanceof`, or ask the registry. A provider's name that is not a valid PHP variable name is still
reachable by its service id or by the registry.

Nothing is built when the container compiles: a provider is built the first time it is asked for.
So a site boots before its accounts are connected; `$registry->has('instagram')` says a provider is
declared, a call inside `try { } catch (InvalidConfigException)` whether it is configured.

A token kept in a database rather than in the environment:
`$registry->create('instagram', ['access_token' => $stored])` builds a provider afresh with those
options over the configured ones, not kept by the registry ([Tokens](usage-tokens.md)).

## An application's own provider

A class of the application that implements `Omnipost\ProviderFactoryInterface` (usually by
extending `Omnipost\ProviderFactory`) is autoconfigured: tagged `omnipost.provider_factory`, it joins
the registry, and its `getName()` can be named as a `factory`:

```yaml
omnipost:
    providers:
        fediverse:
            factory: mastodon     # App\Social\MastodonProviderFactory::getName()
            options: { token: '%env(MASTODON_TOKEN)%' }
```

[Writing a provider](providers.md) says what it must do.

The bundle registers no command, event, route, controller or Twig function. Publishing takes time
(Instagram transcodes, YouTube uploads): run `publish()` and the `status()` polling from a message
handler or a command rather than in a web request ([Publishing](usage-publishing.md#following-a-publication)).
