<?php

namespace Omnipost;

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
 */
abstract class ProviderFactory implements ProviderFactoryInterface
{
    public function __construct(protected readonly ?HttpClientInterface $http = null)
    {
    }

    public function getName(): string
    {
        return $this->createConfig()['omnipost.factory_name'];
    }

    public function create(array $options = []): ProviderInterface
    {
        $config = $this->createConfig($options);
        $config->validateNotEmpty($config->get('omnipost.required_options', []));

        return $this->build($config);
    }

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
