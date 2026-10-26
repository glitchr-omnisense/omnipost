---
title: Errors
order: 50
---

# Errors

Every exception implements `Omnipost\Exception\OmnipostException` (an interface that extends
`\Throwable`).

| Exception | Parent | Thrown when |
|---|---|---|
| `InvalidConfigException` | `\LogicException` | the registry is asked for a name not configured, or one whose factory was not given; a call needs a credential the provider was not given |
| `InvalidPostException` | `\InvalidArgumentException` | `publish()` was given a post the network would refuse: nothing was sent |
| `NotSupportedException` | `\LogicException` | the provider does not do that (Instagram's `delete()`, a kind it cannot publish) |
| `ProviderException` | `\RuntimeException` | the network refused the call, could not be reached, or answered without what was expected |
| `UnauthorizedException` | `ProviderException` | the token is dead or refused: the account must be connected again |

## Properties and messages

| Exception | Properties | Message |
|---|---|---|
| `InvalidPostException` | `provider` (`string`), `violations` (`list<Violation>`) | `[<provider>] <path>: <message>; <path>: <message>` |
| `ProviderException`, `UnauthorizedException` | `provider` (`string`), `status` (`?int`, the HTTP status; null when unreachable), `providerCode` (`?string`, the network's code) | `[<provider>] <the network's message>` |
| `InvalidConfigException` | - | `The "youtube" provider needs: client_id, client_secret, refresh_token (or an access_token) to upload.` |
| `NotSupportedException` | - | `The "instagram" provider does not delete posts (the API cannot: the account does, in the application).` |

There is no "unavailable" or "rate limited" class in this family: a network that cannot be reached,
or asks to slow down, is a `ProviderException` too.

## By provider

Run on 2026-10-26 on mocked answers (the networks' documented error shapes):

```
youtube: ProviderException status=403 code='insufficientPermissions' | [youtube] Request had insufficient authentication scopes.
youtube: ProviderException status=403 code='quotaExceeded' | [youtube] The request cannot be completed because you have exceeded your quota.
youtube: UnauthorizedException status=400 code='invalid_grant' | [youtube] Token has been expired or revoked.
youtube: ProviderException status=NULL code=NULL | [youtube] YouTube could not be reached: Could not resolve host
instagram: UnauthorizedException status=400 code='190' | [instagram] Error validating access token
instagram: ProviderException status=400 code='4' | [instagram] Too many calls, try again later: Application request limit reached
instagram: ProviderException status=NULL code=NULL | [instagram] Instagram could not be reached: timeout for https://graph.instagram.com/v23.0/me?access_token=***
InvalidConfigException: The "youtube" provider needs: api_key and channel_id to read a channel (or client_id, client_secret, refresh_token to read its own).
InvalidConfigException: The "instagram" provider needs: access_token.
InvalidConfigException: The "youtube" provider needs: client_id, client_secret, refresh_token (or an access_token) to upload.
```

**Instagram** (`omnipost/instagram`, `Api::error()`): an answer of 400 or more, or one carrying
`error` / `error_type`, is an exception. HTTP 401, Graph code 190, or subcode 463 or 467 is
`UnauthorizedException`; codes 4, 17, 32 and 613 (Meta's call limits, `Api::RATE_LIMIT_CODES`) a
`ProviderException` whose message starts `Too many calls, try again later:`; anything else a
`ProviderException` with Graph's code as `providerCode`. A transport error is a `ProviderException`
**not chained** to the client's exception, and every `access_token=`, `client_secret=` and `code=`
in a message is masked (`***`): the URL in a transport message carries the token.

**YouTube** (`omnipost/youtube`, `Api::decode()`): an answer of 400 or more, or one carrying
`error`, is an exception. The reason is the API's first `errors[].reason`, or the token endpoint's
`error`. HTTP 401, or the reasons `invalid_grant`, `invalid_client`, `unauthorized_client`,
`authError`, is `UnauthorizedException`; anything else a `ProviderException` with the reason as
`providerCode` (`quotaExceeded`, `insufficientPermissions`...). A transport error is a
`ProviderException` chained to the client's exception. Other cases: no upload URL in the answer,
the video's file unreadable, the video's URL answering other than 200, a status asked for a video
that does not exist (404, `videoNotFound`), a channel not found (404, `channelNotFound`), no
uploads playlist, no token in Google's answer.

> [!WARNING]
> Known defect: YouTube's 403 `insufficientPermissions` (a token without the scope the call needs) is a `ProviderException`, where `UnauthorizedException` - "the token is dead, or lacks a permission" - is documented for it.

## What to catch

```php
use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\InvalidPostException;
use Omnipost\Exception\NotSupportedException;
use Omnipost\Exception\ProviderException;
use Omnipost\Exception\UnauthorizedException;

try {
    $publication = $provider->publish($post->for($provider->getPlatform()));
} catch (InvalidPostException $e) {
    // show $e->violations; nothing was sent
} catch (UnauthorizedException) {
    // ask the account's owner to connect it again
} catch (InvalidConfigException) {
    // this provider is declared, not configured (no token yet)
} catch (NotSupportedException) {
    // this network does not do that
} catch (ProviderException $e) {
    // the network refused, or could not be reached: $e->status, $e->providerCode; retry later
}
```
