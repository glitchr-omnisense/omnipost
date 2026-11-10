<?php

namespace Omnipost;

use Omnipost\Exception\InvalidConfigException;

/**
 * A provider's configuration while its factory builds it: the options given,
 * the factory's defaults under them, and the keys that must be filled.
 * omnisense/contracts' Config with omnipost's prefix ("omnipost.factory_name",
 * "omnipost.required_options", "omnipost.name", "omnipost.limiter",
 * "omnipost.backoff") and omnipost's InvalidConfigException; backoff() gives
 * the provider's wait to its API client.
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    public const PREFIX = 'omnipost';

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }
}
