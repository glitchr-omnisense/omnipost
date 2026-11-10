# Changelog

## [Unreleased]

### Fixed

- A post is never published twice by the site's HTTP client: `ProviderFactory` keeps the client it
  is given (the bundle's `http_client`) with its retries off (omnisense/contracts' `Http\NoRetry`,
  which omnisense/contracts 1568f2c brings; with an older kernel the client is kept as given), so
  Symfony's `RetryableHttpClient` (`retry_failed`) no longer resends a publish answered 429, 502 or
  503. A `MockHttpClient` is kept as given. docs/policies.md. The public API is unchanged.

### Changed

- omnisense/omnipost on omnisense/contracts, the kernel of the omni families, with usage policies;
  the public API unchanged. `Config` and `Http\Redaction` are thin subclasses of the kernel's
  (prefix `omnipost`); `OmnipostException` extends its `ExceptionInterface`,
  `InvalidConfigException` and `NotSupportedException` its own (LogicExceptions as before).
  `ProviderInterface`, `ProviderFactory` and `Registry` stay omnipost's (a feed has no
  capabilities, so a provider is not the kernel's `ProviderInterface`), on the kernel's Config,
  Backoff and PolicyGuard: `ProviderFactory::create()` gives the provider's wait in its Config
  (`Config::backoff()`), over the registry's limiter.
- PHPStan analyses the whole core (the share links alone before); `phpstan-baseline.neon` holds the
  103 errors the rest had when it came in.

### Added

- `Registry::publisher()` and `feed()`: the provider behind its guard (`Policy\GuardedPublisher`,
  `Policy\GuardedFeed`): a call over a limit refused with nothing sent
  (`Exception\UsageLimitReachedException`), counted after unless nothing was sent, never retried;
  `guard()`, `policy()`, `policies()`; `policy` and `usage` beside a provider's options (or in
  them), a shared `RateLimiterInterface` as the registry's third argument; the bundle takes
  `policy`, `usage` and `limiter`.
- `Exception\RateLimitedException` (a `ProviderException`, with `retryAfter`) and `Http\Throttle`:
  a provider's API client keeps the waits its network asks for and refuses the next calls while
  one lasts.
- `docs/policies.md`: each provider's policy, the guard, a site's tightening, why a post is never
  sent twice (and Symfony's `retry_failed`, which would). The harness installs omnisense/contracts
  from its checkout (`OMNISENSE_CONTRACTS`) or GitHub, runs PHPStan (`phpstan`) on every package,
  names its entrypoint and runs on Docker's default bridge.
- CI: `.github/workflows/ci.yml`, glitchr-studio/ci's package job as omnishare had it (the package installed alone,
  PHPUnit, PHPStan, a consumer install that autoloads `Omnipost\Share\Registry`); `phpunit.xml.dist` (the core's
  suite, `Tests/`) and `phpstan-baseline.neon`, empty: any error of the paths phpstan.neon analyses fails the job.
- The share contract, from `omnisense/omnishare` (merged with its history on 2026-11-09): plain
  share links for a page's visitors, with no network script and no cookie, one JSON for Twig, Blade
  and React. `Omnipost\Share\` holds `NetworkInterface`, `Registry` (`defaults()`: Facebook, X,
  Pinterest, LinkedIn, Bluesky, Mastodon, Threads, WhatsApp, Telegram, Reddit, e-mail, copy, the
  device's share sheet), `Shareable`, `Share`, `Link`, `Labels` (English and French,
  `Share/translations/`) and the networks; `Omnipost\Exception\InvalidShareableException` and
  `UnknownNetworkException`; `assets/omnipost-share.js`. From omnishare: `Omnishare\` is
  `Omnipost\Share\`, its exceptions implement `OmnipostException` (`OmnishareException` is gone),
  the label keys `omnishare.*` are `omnipost.share.*`, the script's `data-omnishare*`, `Omnishare`
  and `omnishare:copied` are `data-omnipost-share*`, `OmnipostShare` and `omnipost-share:copied`.
  `omnisense/omnishare` is not replaced (its namespace changed).
- The harness's `bare-share` (the share links in plain PHP) and `js` (the script's tests on node);
  `phpstan.neon`, PHPStan at its highest level on the share links.
- `Omnipost\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`), a
  failure's copy to wrap in its place (`copy()`), a trace without arguments (`withoutArguments()`),
  for the providers that call with a key or a token.

### Fixed

- `Post::$link` is written into the caption by a network whose captions make it a link, and
  counted there by the validator (`Post::text(bool $withLink)`, `Capabilities::$linkInCaption`): both
  were read by nothing.
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
