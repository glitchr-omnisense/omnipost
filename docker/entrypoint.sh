#!/bin/sh
# Composes the harness (this core + every provider), installs it when it
# changed, then runs the command: the console by default, "test" for PHPUnit,
# "composer ..." or "sh" as they are.
set -e
php /omnipost/core/docker/harness/setup.php
cd /harness
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
elif [ composer.json -nt composer.lock ]; then
    composer update --no-interaction --no-progress
fi
case "${1:-}" in
    test) shift; exec vendor/bin/phpunit "$@" ;;
    composer|sh|php) exec "$@" ;;
    *) exec php /omnipost/core/docker/harness/bin/omnipost "$@" ;;
esac
