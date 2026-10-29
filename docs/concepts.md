---
title: Concepts
order: 20
---

# Concepts

## Providers and what they do

A provider is one network, behind `Omnipost\ProviderInterface`:

| Method | |
|---|---|
| `getName(): string` | the name it is configured as: `instagram`, `youtube` |
| `getPlatform(): Platform` | the network |

What it does is said by the other interfaces it implements:

| Interface | Methods | Implemented by |
|---|---|---|
| `Omnipost\PublisherInterface` | `capabilities(): Capabilities`, `publish(Post $post): Publication`, `status(string $publicationId): Publication`, `delete(string $publicationId): void` | Instagram, YouTube |
| `Omnipost\FeedInterface` | `account(): Account`, `feed(?string $cursor = null, int $limit = 25): Feed` (newest first) | Instagram, YouTube |
| `Omnipost\Auth\RefreshableInterface` | `token(): ?Token`, `refresh(): Token` | Instagram, YouTube |
| `Omnipost\Auth\OAuthInterface` | `authorizationUrl(string $redirectUri, string $state, array $scopes = []): string`, `exchange(string $code, string $redirectUri): Token` | Instagram, YouTube |
| `Omnipost\DistributorInterface` | `createRelease(array $release): string`, `deliver(string $releaseId): void`, `deliveryStatus(string $releaseId): array` | none: reserved for delivering releases to streaming services through a distributor (DDEX) |

The contract of a publisher:

- `publish()` checks the post against the network first; a post it would refuse is an
  `InvalidPostException` listing every violation, and nothing is sent.
- It answers with the network's id for the publication and a state that may not be final
  (`PENDING`, `PROCESSING`): `status($id)` reads it again until it is. The id can change on the way
  (Instagram's container id becomes the media's): keep the latest one.
- `delete()` takes the post down, or throws `NotSupportedException` where the network's API cannot.

Both shipped providers also have a public `validate(Post $post): list<Violation>`, not part of an
interface: the core's `Validator` against their capabilities, plus the rules that depend on the
post's kind. It is what their `publish()` runs.

## Factories and their Config

A provider is built by its factory, `Omnipost\ProviderFactoryInterface`: `getName(): string` (the
name providers are configured with) and `create(array $options = []): ProviderInterface`. Both
packages extend `Omnipost\ProviderFactory`:

| Member | |
|---|---|
| `__construct(?HttpClientInterface $http = null)` | the HTTP client the provider calls with; null: the provider package makes its own (`HttpClient::create()`) |
| `getName()` | the `omnipost.factory_name` its `populate()` sets |
| `create($options)` | `createConfig($options)`, checks every key of `omnipost.required_options` is filled, then `build()` |
| `createConfig($options): Config` | the options **without those that are `null` or `''`**, with the factory's defaults under them |
| `populate(Config $config)` (abstract, protected) | the name, the required options, the defaults |
| `build(Config $config)` (abstract, protected) | the provider |

Dropping empty options means an environment variable left unset (`%env(default::X)%`, `env('X')`)
is as if not given: the default stands. Neither shipped factory requires an option, by design: a
provider without its token still answers what needs none (`capabilities()`, `authorizationUrl()`),
and a call that needs a credential and finds none throws `InvalidConfigException` naming it.

`Omnipost\Config` is an `ArrayObject` of the options: `defaults(array)` (sets each key not set),
`validateNotEmpty(array $keys)` (`The "<factory>" provider needs: <keys>.`), `get($key, $default)`.

## The registry

`Omnipost\Registry` holds the providers by name:

```php
$registry = new Registry(
    [new InstagramProviderFactory($http), new YouTubeProviderFactory($http)],
    ['instagram' => ['factory' => 'instagram', 'options' => ['access_token' => '...']]],
);
```

| Method | |
|---|---|
| `get(string $name): ProviderInterface` | the provider, built once and kept. A name not configured or a factory not given is an `InvalidConfigException`: `No "x" provider; configured: instagram.`, `No "x" factory for the "y" provider; installed: instagram, youtube.` |
| `create(string $name, array $overrides = []): ProviderInterface` | a provider built afresh, the configured options with `$overrides` over them (`array_replace`): a token kept in a database. Not kept: `get()` still gives the configured one |
| `has(string $name): bool` | whether the name is configured |
| `options(string $name): array` | its configured options (`[]` when none or not configured) |
| `names(): list<string>` | the configured names, in order |
| `all(): array<string, ProviderInterface>` | every configured provider, built |
| `publishers(): list<PublisherInterface>`, `feeds(): list<FeedInterface>` | those among them that publish, that have a feed (builds them all) |
| `factories(): list<string>` | the factories' names |

## Posts

`Omnipost\Model\Post` is the canonical post, immutable:

| Property | Type | Default | |
|---|---|---|---|
| `kind` | `PostKind` | - | what it is |
| `media` | `list<Media>` | - | the pictures or videos, in order |
| `caption` | `string` | `''` | without the hashtags |
| `title` | `?string` | `null` | for the networks that want one (YouTube) |
| `tags` | `list<string>` | `[]` | hashtags, with or without `#` |
| `coverUrl` | `?string` | `null` | a still for a video (Instagram's `cover_url`) |
| `scheduledAt` | `?\DateTimeImmutable` | `null` | a publication time, where the network takes one (YouTube) |
| `variants` | `array<string, Variant>` | `[]` | platform value => what that network gets instead |
| `options` | `array<string, mixed>` | `[]` | provider-specific extras (`privacy`, `share_to_feed`...): a variant's win |
| `link` | `?string` | `null` | a link to carry (the site's page) |

| Method | |
|---|---|
| `Post::reel(Media $video, string $caption = '', ?string $title = null, array $tags = [])` | a `REEL`, its cover the video's `coverUrl` |
| `Post::image(Media $image, string $caption = '', array $tags = [])` | an `IMAGE` |
| `Post::carousel(array $media, string $caption = '', array $tags = [])` | a `CAROUSEL` |
| `withVariant(Variant $variant)` | a copy with that network's variant (replacing one for the same network) |
| `withScheduledAt(?\DateTimeImmutable $at)` | a copy with that time |
| `variant(Platform $platform): ?Variant` | |
| `for(Platform $platform): Post` | the post as that network receives it: the variant's media, caption, title, tags and cover over the post's, its options over the post's options (`$variant->options + $post->options`), no variants left. Kind, schedule and link stay the post's |
| `text(): string` | the caption, a blank line, the hashtags each with one `#`, space-separated; trimmed |
| `video(): ?Media` | the first video among the media |

`Post::$link` is written into the caption, on its own line before the hashtags, by a network whose captions make it a link (`Capabilities::$linkInCaption`: YouTube's description), and left out by one whose captions do not (Instagram); the validator counts it in the caption's length there.

Publish `$post->for($platform)`, not `$post`: a provider reads the post's own fields, never its
variants.

`Omnipost\Model\Variant`: `platform` (`Platform`), and `caption`, `title`, `tags`, `media`,
`coverUrl` (each null to keep the post's), `options` (`[]`).

`Omnipost\Model\PostKind`, string-backed: `REEL` (`reel`: a vertical short video, the one every
network takes), `IMAGE` (`image`), `CAROUSEL` (`carousel`), `VIDEO` (`video`), `STORY` (`story`).

## Media

`Omnipost\Model\Media` is a picture or a video to post. The networks that fetch it themselves
(Instagram) need `url` to be public - a signed, temporary URL will do; `path` is the local file, for
the providers that upload (YouTube).

| Property | Type | Default | |
|---|---|---|---|
| `kind` | `MediaKind` | - | `IMAGE` (`image`) or `VIDEO` (`video`) |
| `url` | `string` | - | public URL |
| `path` | `?string` | `null` | local file |
| `mime` | `?string` | `null` | `video/mp4`...: the format check needs it |
| `width`, `height` | `?int` | `null` | pixels: the ratio and minimum-size checks need them |
| `duration` | `?float` | `null` | seconds |
| `size` | `?int` | `null` | bytes |
| `coverUrl` | `?string` | `null` | a still for a video |
| `alt` | `?string` | `null` | alternative text (Instagram pictures) |

`Media::image($url, $path, $width, $height, $alt)` and
`Media::video($url, $path, $duration, $width, $height, $coverUrl)` leave `mime` and `size` null:
the validator checks only what is known, so build with `new Media(...)` to have the format and size
checked. `aspectRatio(): ?string` is the reduced ratio (`9:16`, `16:9`; null when the size is
unknown), `ratio(): ?float` width / height.

## Capabilities and violations

`Omnipost\Model\Capabilities` is what a network accepts:

| Property | Type | Default | |
|---|---|---|---|
| `kinds` | `list<PostKind>` | - | the kinds it takes |
| `minDuration`, `maxDuration` | `?float` | `null` | a video's bounds, seconds |
| `aspectRatios` | `list<string>` | `[]` | `9:16`, `4:5`...; empty: any |
| `ratioTolerance` | `float` | `0.01` | a fraction of the ratio (1 %) |
| `maxVideoSize`, `maxImageSize` | `?int` | `null` | bytes |
| `captionMaxLength` | `?int` | `null` | characters, hashtags included |
| `maxTags` | `?int` | `null` | |
| `titleRequired` | `bool` | `false` | |
| `titleMaxLength` | `?int` | `null` | |
| `mediaMaxCount` | `int` | `1` | |
| `videoFormats`, `imageFormats` | `list<string>` | `[]` | mime types or extensions; empty: any |
| `minWidth`, `minHeight` | `?int` | `null` | pixels |
| `scheduling` | `bool` | `false` | whether the network takes a publication time |
| `linkInCaption` | `bool` | `true` | declared; read by no check |

`supports(PostKind $kind): bool`; `acceptsRatio(float $ratio): bool` (true when the list is empty,
or the ratio is within the tolerance of one of them). The values of each network:
[Validating a post](usage-validation.md#what-each-network-accepts).

`Omnipost\Model\Violation`: `path` (`caption`, `media[0].duration`...) and `message`, readable;
`(string) $violation` is `path: message`.

## Publications

`Omnipost\Model\Publication` is what became of a post on one network:

| Property | Type | |
|---|---|---|
| `platform` | `Platform` | |
| `id` | `string` | the network's id: what `status()` and `delete()` take |
| `state` | `PublicationState` | |
| `permalink` | `?string` | once published (YouTube gives it from the start) |
| `error` | `?string` | the network's reason, when failed |
| `publishedAt` | `?\DateTimeImmutable` | |
| `raw` | `array` | the network's own answer, for the record |

`isPublished()`, `isFailed()`. `Omnipost\Model\PublicationState`: `PENDING` (`pending`: handed to the
network, not processed yet; a scheduled video), `PROCESSING` (`processing`: transcoding),
`PUBLISHED` (`published`), `FAILED` (`failed`); `isFinal()` is true for the last two.

## Accounts and feeds

`Omnipost\Model\Account`: `platform`, `id`, `username`, and `name`, `url`, `pictureUrl`,
`followers` (`?int`; null when hidden), `posts` (`?int`), `kind` (`?string`: Instagram's account
type).

`Omnipost\Model\Feed`: `items` (`list<FeedItem>`, newest first) and `next` (`?string`: the cursor
of the next page, null on the last one).

`Omnipost\Model\FeedItem`:

| Property | Type | |
|---|---|---|
| `id` | `string` | |
| `kind` | `PostKind` | |
| `permalink` | `string` | the post's page |
| `url` | `?string` | the media itself (Instagram), the player to embed (YouTube) |
| `thumbnailUrl` | `?string` | |
| `caption` | `?string` | YouTube: the title |
| `publishedAt` | `?\DateTimeImmutable` | |
| `duration` | `?float` | seconds (YouTube) |
| `children` | `list<FeedItem>` | a carousel's items |
| `likes`, `comments`, `views` | `?int` | as the network gives them |
| `raw` | `array` | the network's own answer |

## Tokens

`Omnipost\Model\Token`:

| Member | |
|---|---|
| `accessToken` (`string`), `expiresAt` (`?\DateTimeImmutable`), `refreshToken` (`?string`), `scopes` (`list<string>`), `accountId` (`?string`) | |
| `isExpired(?\DateTimeImmutable $now = null): bool` | past `expiresAt`; never when it is unknown |
| `isExpiring(int $days = 10, ?\DateTimeImmutable $now = null): bool` | within `$days` of `expiresAt`: time to refresh |
| `toArray(): array` | `access_token`, `expires_at` (ATOM), `refresh_token`, `scopes`, `account_id`, unknown ones left out: what a site stores |
| `Token::fromArray(array $data)` | back |

## Platforms

`Omnipost\Platform`, string-backed, with `label()`: `INSTAGRAM` (`instagram`, Instagram), `YOUTUBE`
(`youtube`, YouTube), `FACEBOOK` (`facebook`), `TIKTOK` (`tiktok`, TikTok), `THREADS` (`threads`),
`X` (`x`), `LINKEDIN` (`linkedin`, LinkedIn), `BLUESKY` (`bluesky`), `MASTODON` (`mastodon`),
`PINTEREST` (`pinterest`). Only Instagram and YouTube have a provider; the others can carry
variants for an application's own providers.
