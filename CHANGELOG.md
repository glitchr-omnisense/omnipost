# Changelog

## [Unreleased]

### Added

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
