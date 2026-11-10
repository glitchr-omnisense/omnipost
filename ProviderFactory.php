<?php

namespace Omnipost;

use Omnipost\Exception\UsageLimitReachedException;
use Omnisense\Contracts\Http\NoRetry;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Omnibus way: a network's factory fills a Config - its name
 * ("omnipost.factory_name"), the options it cannot do without
 * ("omnipost.required_options") and the defaults of the others - and builds
 * its provider from it.
 *
 * Options left empty (null, '': an environment variable not set) are as if
 * not given: the defaults stand. A credential is best not required here but
 * asked for when a call needs it, so that a provider without its token still
 * answers what needs none (capabilities(), authorizationUrl()).
 *
 * Before build(), the Config holds the provider's wait (Config::backoff(),
 * omnisense/contracts' Backoff over the registry's limiter, "omnipost.limiter",
 * or an in-memory one): the factory gives it to the provider's API client,
 * which then refuses a call while the network's Retry-After or rate-limit
 * answer lasts. Nothing in it retries: a post refused is never sent again.
 * Nor does the HTTP client given: it is kept with its retries off
 * (omnisense/contracts' Http\NoRetry), so Symfony's RetryableHttpClient never
 * publishes a post twice.
 *
 * Omnipost's providers are not omnisense/contracts' ProviderInterface (they
 * have no getTitle() nor capabilities() of the kernel's shape: a feed has no
 * capabilities at all), so this factory is omnipost's own, on the kernel's
 * Config and Backoff; the registry builds each provider's guard.
 */
abstract class ProviderFactory implements ProviderFactoryInterface
{
    protected readonly ?HttpClientInterface $http;

    public function __construct(?HttpClientInterface $http = null)
    {
        // Http\NoRetry came with omnisense/contracts 1568f2c: a site whose vendor holds an older one keeps its client as given.
        $this->http = null !== $http && class_exists(NoRetry::class) ? NoRetry::of($http) : $http;
    }

    public function getName(): string
    {
        return $this->createConfig()->string('omnipost.factory_name') ?? static::class;
    }

    public function create(array $options = []): ProviderInterface
    {
        $config = $this->createConfig($options);
        $required = $config->get('omnipost.required_options', []);
        $config->validateNotEmpty(\is_array($required) ? array_values(array_filter($required, 'is_string')) : []);
        $limiter = $config->get('omnipost.limiter');
        $config['omnipost.backoff'] = new Backoff($limiter instanceof RateLimiterInterface ? $limiter : new InMemoryRateLimiter(), $config->string('omnipost.factory_name') ?? static::class, UsageLimitReachedException::class);

        return $this->build($config);
    }

    /** @param array<string, mixed> $options */
    public function createConfig(array $options = []): Config
    {
        $config = new Config(array_filter($options, static fn ($value) => null !== $value && '' !== $value));
        $this->populate($config);

        return $config;
    }

    /** The factory's name, required options and defaults. */
    abstract protected function populate(Config $config): void;

    abstract protected function build(Config $config): ProviderInterface;
}
