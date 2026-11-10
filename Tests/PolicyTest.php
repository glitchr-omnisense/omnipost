<?php

namespace Omnipost\Tests;

use Omnipost\Config;
use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\OmnipostException;
use Omnipost\Exception\ProviderException;
use Omnipost\Exception\UsageLimitReachedException;
use Omnipost\Model\Capabilities;
use Omnipost\Model\Media;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Publication;
use Omnipost\Model\PublicationState;
use Omnipost\Platform;
use Omnipost\Policy\GuardedFeed;
use Omnipost\Policy\GuardedPublisher;
use Omnipost\ProviderFactory;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;
use Omnipost\Registry;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Usage policies through the registry: publisher() and feed() behind the
 * guard, a site's tightening, what the site is, and a post never sent twice.
 */
final class PolicyTest extends TestCase
{
    private static function reel(): Post
    {
        return Post::reel(Media::video('https://site.example/reel.mp4'));
    }

    public function testASitesLimitRefusesThePostOverItWithNothingSent(): void
    {
        $registry = new Registry([new StubFactory()], [
            'fediverse' => ['factory' => 'stub', 'options' => ['token' => 'a'], 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'day', 'scope' => 'key', 'operations' => ['publish']]]]],
        ]);
        $publisher = $registry->publisher('fediverse');
        self::assertInstanceOf(GuardedPublisher::class, $publisher);

        $publisher->publish(self::reel());
        $publisher->publish(self::reel());
        self::assertSame(0, $registry->guard('fediverse')->remaining('publish', ['key' => 'fediverse']));
        try {
            $publisher->publish(self::reel());
            self::fail('A third post is over the limit.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnipostException::class, $e);
            self::assertGreaterThan(0, $e->retryAfter);
        }
        $stub = $registry->get('fediverse');
        self::assertInstanceOf(StubPublisher::class, $stub);
        self::assertSame(2, $stub->published, 'the third never reached the network');
        self::assertSame(PublicationState::PUBLISHED, $publisher->status('x')->state, 'status() is not counted by a publish limit');
    }

    public function testAFreshProviderWithAStoredTokenCountsUnderTheSameName(): void
    {
        $registry = new Registry([new StubFactory()], [
            'fediverse' => ['factory' => 'stub', 'options' => ['token' => 'a', 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'scope' => 'key', 'operations' => ['publish']]]]]],
        ]);
        $registry->publisher('fediverse')->publish(self::reel());

        $this->expectException(UsageLimitReachedException::class);
        $registry->publisher('fediverse', ['token' => 'b'])->publish(self::reel());
    }

    public function testAPublishThatFailsIsSentOnceAndCounted(): void
    {
        $failing = new FailingPublisher();
        $registry = new Registry([new FixedFactory($failing)], ['flaky' => ['factory' => 'fixed']]);
        try {
            $registry->publisher('flaky')->publish(self::reel());
            self::fail('The network failed.');
        } catch (ProviderException $e) {
            self::assertSame(503, $e->status);
        }
        self::assertSame(1, $failing->calls, 'never retried by the guard');
        self::assertSame(98, $registry->guard('flaky')->remaining('publish'), 'and counted, as the network counted it');
    }

    public function testAPostRefusedBeforeAnythingIsSentIsNotCounted(): void
    {
        $refusing = new FailingPublisher(new \Omnipost\Exception\InvalidPostException('failing', [new \Omnipost\Model\Violation('media', 'A reel is a video.')]));
        $registry = new Registry([new FixedFactory($refusing)], ['refusing' => ['factory' => 'fixed']]);
        try {
            $registry->publisher('refusing')->publish(self::reel());
            self::fail('Refused.');
        } catch (\Omnipost\Exception\InvalidPostException) {
            // Checked against the capabilities: nothing sent.
        }
        self::assertSame(1, $refusing->calls);
        self::assertSame(99, $registry->guard('refusing')->remaining('publish'), 'not counted');
    }

    public function testTheDeclaredPolicyIsTightenedNeverLoosened(): void
    {
        $registry = new Registry([new FixedFactory(new FailingPublisher())], [
            'tight' => ['factory' => 'fixed', 'policy' => ['rateLimits' => [['max' => 10, 'window' => 'day', 'operations' => ['publish']]], 'rights' => ['caching' => ['permission' => 'forbidden']]]],
            'loose' => ['factory' => 'fixed', 'policy' => ['rateLimits' => [['max' => 1000, 'window' => 'day', 'operations' => ['publish']]]]],
        ]);

        $policy = $registry->policy('tight');
        self::assertSame('failing', $policy->provider);
        self::assertSame([99, 10], array_map(static fn (RateLimit $l) => $l->max, $policy->limitsFor('publish')));
        self::assertSame(Permission::Forbidden, $policy->rights->caching->permission);

        $this->expectException(InvalidPolicyException::class);
        $registry->policy('loose');
    }

    public function testACommercialSiteIsRefusedWhereTheTermsForbidIt(): void
    {
        $registry = new Registry([new FixedFactory(new FailingPublisher())], ['shop' => ['factory' => 'fixed', 'usage' => ['commercial' => true]]]);

        $this->expectException(UsageNotPermitted::class);
        $registry->publisher('shop');
    }

    public function testAProviderWithoutAPolicyHasAnUnknownOneAndTheFeedIsGuardedToo(): void
    {
        $limiter = new InMemoryRateLimiter();
        $registry = new Registry([new StubWallFactory()], ['wall' => ['factory' => 'wall', 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['feed']]]]]], $limiter);

        self::assertFalse(UsagePolicy::unknown('wall')->knowsLimits());
        self::assertSame(['wall'], array_keys($registry->policies()));
        $feed = $registry->feed('wall');
        self::assertInstanceOf(GuardedFeed::class, $feed);
        $feed->account();
        $feed->account();
        $feed->feed();
        $this->expectException(UsageLimitReachedException::class);
        // Another registry over the same limiter: the count is the limiter's.
        (new Registry([new StubWallFactory()], ['wall' => ['factory' => 'wall', 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['feed']]]]]], $limiter))->feed('wall')->feed();
    }

    public function testAFeedIsNotAPublisher(): void
    {
        $registry = new Registry([new StubWallFactory()], ['wall' => ['factory' => 'wall']]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "wall" provider does not publish.');
        $registry->publisher('wall');
    }

    public function testTheFactoryGivesTheProviderItsWait(): void
    {
        $config = (new FixedFactory(new FailingPublisher()))->createConfig();
        self::assertNull($config->backoff(), 'outside create()');
        $factory = new FixedFactory(new FailingPublisher());
        $factory->create();
        self::assertNotNull($factory->config?->backoff());
        self::assertSame('omnipost.backoff', $factory->config->key('backoff'));
    }
}

/** A network that declares its policy: 99 posts a day, no commercial use, and every publish answered 503. */
final class FailingPublisher implements PublisherInterface, DeclaresUsagePolicyInterface
{
    public int $calls = 0;

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function usagePolicy(): UsagePolicy
    {
        return UsagePolicy::builder('failing')->limit(RateLimit::perDay(99, 'publish'))->commercial(Permission::Forbidden)->build();
    }

    public function getName(): string
    {
        return 'failing';
    }

    public function getPlatform(): Platform
    {
        return Platform::MASTODON;
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities([PostKind::REEL]);
    }

    public function publish(Post $post): Publication
    {
        ++$this->calls;

        throw $this->failure ?? new ProviderException('failing', 'Service unavailable.', 503);
    }

    public function status(string $publicationId): Publication
    {
        throw new ProviderException('failing', 'Service unavailable.', 503);
    }

    public function delete(string $publicationId): void
    {
    }
}

/** A factory that gives the provider it was handed, and keeps the Config it built it from. */
final class FixedFactory extends ProviderFactory
{
    public ?Config $config = null;

    public function __construct(private readonly ProviderInterface $provider)
    {
        parent::__construct();
    }

    protected function populate(Config $config): void
    {
        $config->defaults(['omnipost.factory_name' => 'fixed']);
    }

    protected function build(Config $config): ProviderInterface
    {
        $this->config = $config;

        return $this->provider;
    }
}
