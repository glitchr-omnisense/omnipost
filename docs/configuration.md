---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry

```php
new Registry(iterable $factories, array $config);
```

| Key | Type | Required | |
|---|---|---|---|
| `<name>` | string | - | the provider's name: `get('<name>')`, and in Symfony the argument's name |
| `<name>.factory` | string | yes | a factory's `getName()`: `instagram`, `youtube`, or an application's own |
| `<name>.options` | map | no, default `[]` | handed to the factory's `create()`; `create($name, $overrides)` puts other values over them |

The same tree is the Symfony bundle's `omnipost.providers` ([Symfony](symfony.md#configuration)).
Two accounts on the same network are two names on the same factory:

```php
new Registry([new InstagramProviderFactory($http)], [
    'instagram_artist' => ['factory' => 'instagram', 'options' => ['access_token' => $artistToken]],
    'instagram_label' => ['factory' => 'instagram', 'options' => ['access_token' => $labelToken]],
]);
```

**An option that is `null` or `''` is as if not given** (`ProviderFactory::createConfig()`): the
default stands. No option of either provider is required to build it; a call that needs a missing
credential throws `InvalidConfigException` naming what it needs.

## Instagram (`instagram`)

From `Omnipost\Instagram\InstagramProviderFactory`:

| Option | Type | Default | Needed for | |
|---|---|---|---|---|
| `access_token` | string | none | every call but `authorizationUrl()`, `exchange()` | the long-lived token (60 days) |
| `app_id` | string | none | `authorizationUrl()`, `exchange()` | the Instagram app's id |
| `app_secret` | string | none | `exchange()` | the Instagram app's secret |
| `user_id` | string | `me` | - | the account the calls are made for: `<user_id>/media`, `<user_id>/media_publish` |
| `share_to_feed` | bool | `true` | - | a reel also shown on the profile's grid; read with `FILTER_VALIDATE_BOOL` (`'false'`, `'0'`, `'off'` are false). A post's `share_to_feed` option overrides it |
| `api_version` | string | `v23.0` (`Api::VERSION`) | - | the Graph API version in the path; `''` is the default too (empty is dropped) |
| `base_uri` | string | `https://graph.instagram.com` (`Api::BASE_URI`) | - | the API's root |

Post options it reads (`Post::$options`, or a variant's): `share_to_feed` (bool), `thumb_offset`
(milliseconds into the reel for its cover, sent as given).

## YouTube (`youtube`)

From `Omnipost\YouTube\YouTubeProviderFactory`:

| Option | Type | Default | Needed for | |
|---|---|---|---|---|
| `api_key` | string | none | reading another channel, or reading without OAuth | sent as `X-Goog-Api-Key` |
| `channel_id` | string | none | - | the channel read (`UC...`); without it, the channel the OAuth token belongs to (`mine=true`) |
| `client_id` | string | none | uploading, deleting, `authorizationUrl()`, `exchange()`, `refresh()` | the Google OAuth client |
| `client_secret` | string | none | the same but `authorizationUrl()` | |
| `refresh_token` | string | none | uploading, deleting, `refresh()` (with the two above) | what `exchange()` gave: access tokens are drawn from it |
| `access_token` | string | none | an alternative to the three above | a token at hand (an hour's life), used until refused |
| `privacy` | string | `private` | - | what an upload is set to: `private`, `unlisted`, `public`. A post's `privacy` option overrides it |
| `category_id` | string | none | - | the video category (`10` is Music); YouTube's default otherwise. A post's `category_id` option overrides it |
| `base_uri` | string | `https://www.googleapis.com/youtube/v3` | - | the Data API's root |
| `upload_uri` | string | `https://www.googleapis.com/upload/youtube/v3` | - | the upload root |

Which credential a call uses:

| Call | With `channel_id` | Without `channel_id` |
|---|---|---|
| `account()`, `feed()` | the key; without one, the OAuth token | the OAuth token (`mine=true`); without one, `InvalidConfigException` |
| `status()` | the OAuth token when it can, else the key | the same |
| `publish()`, `delete()` | the OAuth token, required | the same |

Post options it reads: `privacy`, `category_id`.

The token endpoint (`https://oauth2.googleapis.com/token`) and the consent page
(`https://accounts.google.com/o/oauth2/v2/auth`) are constants, not options.

## Environment variables

The packages read no environment variable themselves. The names used in these pages, in the
bundle's docblock and in the harness are conventions: `INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_APP_ID`,
`INSTAGRAM_APP_SECRET`, `YOUTUBE_API_KEY`, `YOUTUBE_CHANNEL_ID`, `YOUTUBE_CLIENT_ID`,
`YOUTUBE_CLIENT_SECRET`, `YOUTUBE_REFRESH_TOKEN`; `OMNIPOST_PLUGINS` for the harness.
