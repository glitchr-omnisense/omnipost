# Changelog

## [Unreleased]

### Added

- `Omnipost\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`), a
  failure's copy to wrap in its place (`copy()`), a trace without arguments (`withoutArguments()`),
  for the providers that call with a key or a token.

### Fixed

- `Post::$link` is written into the caption by a network whose captions make it a link, and
  counted there by the validator (`Post::text(bool $withLink)`, `Capabilities::$linkInCaption`): both
  were read by nothing.
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
