---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-11-09 on the three packages' checkouts (the harness's `docker/harness/setup.php`,
each provider from its checkout), PHP 8.4.27, PHPUnit 11.5.57, no network: 92 tests, 548
assertions, green - the share links' 26 among them ([below](#share-links)).

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
- The defects found while writing these pages, fixed since, each with its test: YouTube's 403
  `insufficientPermissions` not an `UnauthorizedException` ([Errors](errors.md#by-provider));
  `Post::$link` and `Capabilities::$linkInCaption` read by no provider ([Concepts](concepts.md#posts));
  `ext-mbstring` not required by the core.

## Share links

- **Tests.** `Tests/Share/` (26 tests, on 2026-11-09 in the harness, PHP 8.4): every network's
  address for one page, with and without text, picture, hashtags and account; the registry, the
  JSON; in plain PHP, Composer's autoloader alone, nothing loaded but `Omnipost\Share`
  (`BareTest`). PHPStan at its highest level on `Share/`, its exceptions and its tests, no error.
  `assets/omnipost-share.js` on node's test runner (6 tests green, `docker compose run --rm js`).
  The same tests, under `Omnishare\`, passed in `omnisense/omnishare` before the merge, where the
  script was also run in a headless Chrome (the share sheet and the copy revealed, the address
  copied, the sheet given the title and address, a Mastodon instance asked and opened); the script
  has not been run in a browser since its attributes were renamed.
- **Live, signed out**, on 2026-10-23 in a headless Chrome ([The networks](share-networks.md#what-each-network-was-seen-to-do)):
  WhatsApp and Telegram show the title and the address on their share page; Facebook, X, Pinterest,
  LinkedIn, Threads and Reddit keep the address (and, for those that take them, the title, picture,
  hashtags and account) through their sign-in page; Mastodon's `/share` exists and asks to sign in
  first; Bluesky's app recognises `/intent/compose` and, signed out, leaves it for its home page.
- **In their source**, on 2026-10-24 ([The networks](share-networks.md#mastodon-and-bluesky-in-their-source-2026-10-24)):
  Mastodon's `/share` is its composer behind the sign-in, filled with `title`, `text` and `url`
  joined by spaces; Bluesky's `/intent/compose` opens the composer with `text` for a visitor signed
  in, and drops it for one signed out.
- **On an application**, before the merge: lesringostars built its articles' Facebook, X, Pinterest
  and e-mail links with `omnisense/omnishare`, under Symfony, under its Laravel twin and in its
  React screen. It has not moved to `Omnipost\Share` yet.
- **Not verified**: what each network's composer shows once signed in (no account was used;
  Mastodon's and Bluesky's read in their code, not seen); Mastodon's return to the share page after
  its sign-in (Devise's default, read, not seen); the e-mail in a mail client other than the
  browser's handler; Mastodon instances other than mastodon.social and piaille.fr.

The share links are affiliated with none of the networks they link to; their names and colours are
their owners'.

## Responsibility

`omnisense/omnipost` and its `omnipost/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the networks it reaches. Each network keeps its own
terms (apps and their review, tokens, publishing limits, what may be posted), named with a link in
each provider's README. The MIT licence covers this code only and grants no right to any service or
to its data; whoever runs these packages complies with those terms.
