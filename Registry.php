<?php

namespace Omnipost;

use Omnipost\Exception\InvalidConfigException;

/**
 * The providers by name, each built once from its factory and options:
 *
 *   new Registry([new InstagramProviderFactory($http)], [
 *       'instagram' => ['factory' => 'instagram', 'options' => ['access_token' => '...']],
 *   ]);
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                     $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): ProviderInterface
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));
        $factory = $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));

        return $this->providers[$name] = $factory->create($provider['options'] ?? []);
    }

    /**
     * A provider built afresh, its configured options with $overrides over
     * them - a token kept in a database, say. Not kept: get() still gives
     * the configured one.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): ProviderInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));
        $factory = $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));

        return $factory->create(array_replace($provider['options'] ?? [], $overrides));
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** @return array<string, mixed> */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return array<string, ProviderInterface> */
    public function all(): array
    {
        $all = [];
        foreach (array_keys($this->config) as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** @return list<PublisherInterface> */
    public function publishers(): array
    {
        return array_values(array_filter($this->all(), static fn (ProviderInterface $p) => $p instanceof PublisherInterface));
    }

    /** @return list<FeedInterface> */
    public function feeds(): array
    {
        return array_values(array_filter($this->all(), static fn (ProviderInterface $p) => $p instanceof FeedInterface));
    }

    /** @return list<string> the factories installed */
    public function factories(): array
    {
        return array_keys($this->factories);
    }
}
