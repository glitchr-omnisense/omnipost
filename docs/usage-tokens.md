---
title: Tokens and connecting an account
order: 34
---

# Tokens and connecting an account

The tokens stay with the site. A provider is built from the options it is given, connects an
account through OAuth when asked, refreshes a token when asked, and hands back a `Token` for the
site to store; it never stores anything itself.

## Connecting an account (OAuth)

Both providers implement `Auth\OAuthInterface`. A back office sends the account's owner to
`authorizationUrl()`, and on the way back exchanges the code:

```php
// The "connect" button
$state = bin2hex(random_bytes(16));            // kept in the session, checked on the way back
return new RedirectResponse($instagram->authorizationUrl('https://site.example/connect/instagram', $state));

// The redirect URI
if (!hash_equals($session->get('state'), $request->query->get('state'))) { /* refuse */ }
$token = $instagram->exchange($request->query->get('code'), 'https://site.example/connect/instagram');
$store->save('instagram', $token->toArray());
```

The URLs, as the providers build them (run on 2026-10-26):

```
https://www.instagram.com/oauth/authorize?client_id=990001&redirect_uri=https%3A%2F%2Fsite.example%2Fconnect%2Finstagram&scope=instagram_business_basic%2Cinstagram_business_content_publish&response_type=code&state=csrf123
https://accounts.google.com/o/oauth2/v2/auth?client_id=cid.apps.googleusercontent.com&redirect_uri=https%3A%2F%2Fsite.example%2Fconnect%2Fyoutube&response_type=code&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fyoutube.upload+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fyoutube.readonly&access_type=offline&prompt=consent&state=csrf123
```

| | Instagram | YouTube |
|---|---|---|
| Needs | `app_id` (and `app_secret` to exchange) | `client_id` (and `client_secret` to exchange) |
| Default scopes | `instagram_business_basic`, `instagram_business_content_publish` (`InstagramProvider::SCOPES`) | `youtube.upload`, `youtube.readonly` (`YouTubeProvider::SCOPES`) |
| Exchange | the code (its trailing `#_` removed) for a short-lived token at `https://api.instagram.com/oauth/access_token`, then that one for the long-lived token (`GET /access_token?grant_type=ig_exchange_token`) | the code at `https://oauth2.googleapis.com/token` (`grant_type=authorization_code`) |
| Token answered | the long-lived token, its end (60 days), the permissions granted as `scopes`, the account's id as `accountId` | the access token (an hour), its end, **the refresh token** to keep, the scopes |

The `$scopes` argument replaces the default scopes. The `state` is the caller's: generate it, keep
it, and check it on the way back; the providers do not.

After `exchange()` the provider object calls with the new token from then on.

## Refreshing

Both providers implement `Auth\RefreshableInterface`:

```php
$token = $instagram->refresh();               // and the provider uses it from now on
$store->save('instagram', $token->toArray());
```

- **Instagram**: the long-lived token lives 60 days. `refresh()` calls
  `GET /refresh_access_token?grant_type=ig_refresh_token` and gives 60 more days to a token at least
  24 hours old and not dead yet; past its end, the account is connected again. Run it from a monthly
  job. `token()` is the token held, with no end known (`expiresAt` null) unless it came from
  `refresh()` or `exchange()`.
- **YouTube**: the access token lives an hour, drawn from the refresh token (`client_id`,
  `client_secret`, `refresh_token` options). The provider draws one before the first call that
  needs it and again within a minute of its end, by itself: `refresh()` is there to force it.
  `token()` is null until one was drawn. An `access_token` option given without a refresh token has
  no known end: it is used until Google refuses it (`UnauthorizedException`).

Run on 2026-10-26 on a mock answering as Instagram documents it:

```
> GET https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=***
refreshed: IGQ-new, until 2026-12-25, expiring within 10 days: no
["access_token","expires_at"]
```

## Storing a token

`Token::toArray()` keeps what is known (`access_token`, `expires_at` in ATOM, `refresh_token`,
`scopes`, `account_id`), `Token::fromArray()` reads it back. A provider is built with a stored token
by passing it as an option over the configured ones:

```php
$stored = Token::fromArray($store->load('instagram'));
$instagram = $registry->create('instagram', ['access_token' => $stored->accessToken]);

if ($stored->isExpiring(10)) {                 // within ten days of its end
    $store->save('instagram', $instagram->refresh()->toArray());
}
```

`Registry::create()` builds a provider afresh and does not keep it: `get('instagram')` is still the
configured one. For YouTube, store the refresh token and pass it as `refresh_token`.

## A dead token

A token that is expired, revoked or refused is an `UnauthorizedException` (`$e->status`,
`$e->providerCode`): the account must be connected again. Instagram: HTTP 401, error code 190, or
the subcodes 463 and 467. YouTube: HTTP 401, or the reasons `invalid_grant` (a revoked refresh
token), `invalid_client`, `unauthorized_client`, `authError`, and a 403 `insufficientPermissions` (a token without the scope the call needs).

