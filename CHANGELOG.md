# Changelog

## [Unreleased]

### Fixed

- `Post::$link` is written into the caption by a network whose captions make it a link, and
  counted there by the validator (`Post::text(bool $withLink)`, `Capabilities::$linkInCaption`): both
  were read by nothing.
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
