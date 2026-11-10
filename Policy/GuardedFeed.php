<?php

namespace Omnipost\Policy;

use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\NotSupportedException;
use Omnipost\Exception\UsageLimitReachedException;
use Omnipost\FeedInterface;
use Omnipost\Model\Account;
use Omnipost\Model\Feed;
use Omnipost\Platform;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A feed behind its usage policy's guard (Registry::feed()): account() and
 * feed() checked before, counted after, under the configured name; a call
 * that sent nothing (a credential missing, a wait still running) is not
 * counted.
 */
final class GuardedFeed implements FeedInterface
{
    public function __construct(
        private readonly FeedInterface $provider,
        private readonly PolicyGuard $guard,
        private readonly string $name,
    ) {
    }

    /** The provider itself, unguarded. */
    public function provider(): FeedInterface
    {
        return $this->provider;
    }

    public function guard(): PolicyGuard
    {
        return $this->guard;
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function getPlatform(): Platform
    {
        return $this->provider->getPlatform();
    }

    public function account(): Account
    {
        return $this->run('account', fn (): Account => $this->provider->account());
    }

    public function feed(?string $cursor = null, int $limit = 25): Feed
    {
        return $this->run('feed', fn (): Feed => $this->provider->feed($cursor, $limit));
    }

    /**
     * @template T
     *
     * @param \Closure(): T $call
     *
     * @return T
     */
    private function run(string $operation, \Closure $call): mixed
    {
        $context = ['key' => $this->name];
        $this->guard->check($operation, $context);
        $sent = true;
        try {
            return $call();
        } catch (InvalidConfigException|NotSupportedException|UsageLimitReachedException $e) {
            $sent = false;

            throw $e;
        } finally {
            if ($sent) {
                $this->guard->record($operation, $context);
            }
        }
    }
}
