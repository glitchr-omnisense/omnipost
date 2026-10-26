# Symfony

Omnipost runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` - are not required by `omnisense/omnipost`: the application has them, and
nothing of them is loaded outside Symfony.

Register `Omnipost\Bridge\Symfony\OmnipostBundle` (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnipost\Bridge\Symfony\OmnipostBundle::class => ['all' => true],
];
```

```yaml
# config/packages/omnipost.yaml
omnipost:
    providers:                     # by name: a factory and its options
        instagram: { factory: instagram, options: { access_token: '%env(default::INSTAGRAM_ACCESS_TOKEN)%', app_id: '%env(default::INSTAGRAM_APP_ID)%', app_secret: '%env(default::INSTAGRAM_APP_SECRET)%' } }
        youtube:   { factory: youtube, options: { api_key: '%env(default::YOUTUBE_API_KEY)%', channel_id: '%env(default::YOUTUBE_CHANNEL_ID)%' } }
```

Every `omnipost/*` package installed registers its factory, on the application's `http_client`.
What is autowired:

| Service | |
|---|---|
| `PublisherInterface $instagram`, `FeedInterface $youtube` | one provider by the argument's name (the configured name), as what it does |
| `Registry` | every configured provider by name (`get()`, `publishers()`, `feeds()`, `create()` with other options) |
| `Validator` | what a network would refuse, before anything is sent |

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

An application's own provider - a class implementing `ProviderFactoryInterface` - is registered
too, autoconfigured, and can be named as a `factory`.
