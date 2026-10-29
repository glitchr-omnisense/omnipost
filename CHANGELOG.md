# Changelog

## [Unreleased]

### Fixed

- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
