---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-26 on the three packages' checkouts (the workspace's autoloader), PHP 8.4.27,
PHPUnit 11.5.57, no network: 62 tests, 355 assertions, green.

- The core (`Tests/`): the registry (each provider built once by name, publishers and feeds among
  them, a provider built afresh with a token from elsewhere, unknown names and factories, required
  options checked when asked for, an empty option is its default), capabilities and ratios within
  their tolerance, posts and their variants (`for()`, `text()`, `video()`, a scheduled copy),
  tokens (expiry, expiring, to an array and back), the validator rule by rule, the Symfony bundle
  (`Tests/Bridge/OmnipostBundleTest.php`: a kernel boots, providers injectable by name, a provider
  with no token breaks nothing until a call needs it), and the plain-PHP run with no framework class
  loaded (`Tests/BareTest.php`).
- `omnipost/instagram` (12 tests): a reel in three steps, a container in error, a carousel as a
  container per item then theirs, a picture and a story, a refused post sends nothing, the feed's
  kinds, the account, the long-lived token refreshed and used from then, OAuth, the errors mapped,
  what works without a token, nothing deleted.
- `omnipost/youtube` (12 tests): the feed as the uploads playlist with the videos' durations, ISO
  durations, the account as the channel, the token's own channel without a channel id, a Short
  uploaded in two steps with one access token drawn, a video with only a URL brought then uploaded,
  a scheduled video private with its time, the status as the upload's status, a Short without a
  title refused before any request, tokens and OAuth, a revoked grant unauthorized, what works
  without credentials.

The answers these tests give the providers are written in the shapes the networks document; they
were not recorded from the networks. The examples of these pages were run on 2026-10-26 on the same
checkouts with a `MockHttpClient`.

## What is not verified

- No network was called for these manuals, and the READMEs and the history state no live run:
  neither a publication on Instagram or YouTube, nor a feed read from a real account, nor an OAuth
  exchange. The harness can (`docker compose run --rm omnipost publish ...` with real tokens in
  `docker/.env`).
- Instagram's publishing cap per 24 hours, Meta's App Review and YouTube's audit (videos locked
  private) are what the READMEs say of the networks; the code does not check them.
- The [Laravel](laravel.md) page was written from the registry's API; it was not run in a Laravel
  application.
- The known defects found while writing these pages: YouTube's 403 `insufficientPermissions` not an
  `UnauthorizedException` ([Errors](errors.md#by-provider)); `Post::$link` and
  `Capabilities::$linkInCaption` read by no provider ([Concepts](concepts.md#posts)); the core does
  not require `ext-mbstring` though the validator calls `mb_strlen()`
  ([Installation](installation.md#requirements)).

## Responsibility

`omnisense/omnipost` and its `omnipost/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the networks it reaches. Each network keeps its own
terms (apps and their review, tokens, publishing limits, what may be posted), named with a link in
each provider's README. The MIT licence covers this code only and grants no right to any service or
to its data; whoever runs these packages complies with those terms.
