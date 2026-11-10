<?php

namespace Omnipost;

use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\UsageLimitReachedException;
use Omnipost\Policy\GuardedFeed;
use Omnipost\Policy\GuardedPublisher;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Policy\Usage;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * The providers by name, each built once from its factory and options:
 *
 *   new Registry([new InstagramProviderFactory($http)], [
 *       'instagram' => [
 *           'factory' => 'instagram',
 *           'options' => ['access_token' => '...'],
 *           'policy' => ['rateLimits' => [['max' => 20, 'window' => 'day', 'scope' => 'key', 'operations' => ['publish']]]],
 *           'usage' => ['commercial' => true],
 *       ],
 *   ], $limiter);
 *
 * Each provider has a usage policy (omnisense/contracts): the one its
 * package declares (policy.json), tightened by the site's "policy" (never
 * looser), checked against what the site says it is ("usage"); both may sit
 * in "options" too. publisher() and feed() give the provider behind its
 * guard: a call over a limit is refused before anything is sent
 * (UsageLimitReachedException), every call counted after, failed or not,
 * and nothing is ever retried. get(), all(), publishers() and feeds() give
 * the providers themselves, unguarded, as before. Every provider counts in
 * the limiter given (an in-memory one by default): a site served by many
 * processes gives a shared one (omnibridge/symfony's, over symfony/rate-limiter).
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /** @var array<string, PolicyGuard> */
    private array $guards = [];

    private readonly RateLimiterInterface $limiter;

    /**
     * @param iterable<ProviderFactoryInterface>                                                                                                $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config, ?RateLimiterInterface $limiter = null)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
        $this->limiter = $limiter ?? new InMemoryRateLimiter();
    }

    public function get(string $name): ProviderInterface
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));
        $factory = $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));

        return $this->providers[$name] = $factory->create($this->built($name, $provider['options'] ?? []));
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

        return $factory->create($this->built($name, array_replace($provider['options'] ?? [], $overrides)));
    }

    /**
     * The publisher behind its guard: publish(), status() and delete()
     * refused before anything is sent when a limit of its policy is reached
     * (UsageLimitReachedException), counted after, failed or not, never
     * retried; capabilities() as the provider says. With $overrides, a
     * provider built afresh as create() builds it (a token kept in a
     * database), counted under the same name.
     *
     * @param array<string, mixed> $overrides
     */
    public function publisher(string $name, array $overrides = []): PublisherInterface
    {
        $provider = [] === $overrides ? $this->get($name) : $this->create($name, $overrides);
        if (!$provider instanceof PublisherInterface) {
            throw new InvalidConfigException(\sprintf('The "%s" provider does not publish.', $name));
        }

        return new GuardedPublisher($provider, $this->guard($name), $name);
    }

    /**
     * The feed behind its guard: account() and feed() refused before they
     * are called when a limit is reached, counted after.
     *
     * @param array<string, mixed> $overrides
     */
    public function feed(string $name, array $overrides = []): FeedInterface
    {
        $provider = [] === $overrides ? $this->get($name) : $this->create($name, $overrides);
        if (!$provider instanceof FeedInterface) {
            throw new InvalidConfigException(\sprintf('The "%s" provider has no feed.', $name));
        }

        return new GuardedFeed($provider, $this->guard($name), $name);
    }

    /**
     * A provider's guard: its policy as the site runs it, the limiter's
     * counts, the waits the network asked for.
     *
     * @throws UsageNotPermitted when the site's "usage" says a use the policy forbids
     */
    public function guard(string $name): PolicyGuard
    {
        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }
        $provider = $this->get($name);
        $declared = $provider instanceof DeclaresUsagePolicyInterface ? $provider->usagePolicy() : UsagePolicy::unknown($provider->getName());
        if (UsagePolicy::UNNAMED === $declared->provider) {
            $declared = $declared->named($provider->getName());
        }
        $policy = $declared->tighten($this->entry($name, 'policy'));
        $usage = $this->entry($name, 'usage');
        if ([] !== $usage && [] !== $violations = $policy->violations(Usage::fromArray($usage))) {
            throw UsageNotPermitted::of($policy->provider, $violations);
        }

        return $this->guards[$name] = new PolicyGuard($policy, $this->limiter, null, UsageLimitReachedException::class);
    }

    /** A provider's usage policy as the site runs it: its package's, tightened by the site's configuration. */
    public function policy(string $name): UsagePolicy
    {
        return $this->guard($name)->policy();
    }

    /**
     * Every configured provider's policy, by name: what a site's page on the
     * networks it publishes to lists.
     *
     * @return array<string, UsagePolicy>
     */
    public function policies(): array
    {
        $policies = [];
        foreach ($this->names() as $name) {
            $policies[$name] = $this->policy($name);
        }

        return $policies;
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

    /**
     * The options a provider is built with: its own, its name and the
     * registry's limiter (for its wait, Config::backoff()).
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function built(string $name, array $options): array
    {
        return ['omnipost.name' => $name, 'omnipost.limiter' => $this->limiter] + $options;
    }

    /**
     * The site's "policy" or "usage" of a provider, beside its options or in them.
     *
     * @return array<string, mixed>
     */
    private function entry(string $name, string $key): array
    {
        $value = $this->config[$name][$key] ?? $this->config[$name]['options'][$key] ?? [];
        if (!\is_array($value)) {
            throw new InvalidConfigException(\sprintf('The "%s" provider\'s "%s" must be a map, %s given.', $name, $key, get_debug_type($value)));
        }
        $map = [];
        foreach ($value as $one => $setting) {
            $map[(string) $one] = $setting;
        }

        return $map;
    }
}
