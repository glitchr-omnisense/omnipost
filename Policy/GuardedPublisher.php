<?php

namespace Omnipost\Policy;

use Omnipost\Exception\InvalidConfigException;
use Omnipost\Exception\InvalidPostException;
use Omnipost\Exception\NotSupportedException;
use Omnipost\Exception\UsageLimitReachedException;
use Omnipost\Model\Capabilities;
use Omnipost\Model\Post;
use Omnipost\Model\Publication;
use Omnipost\Platform;
use Omnipost\PublisherInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A publisher behind its usage policy's guard (Registry::publisher()): each
 * call an operation named after its method (publish, status, delete),
 * checked before - a limit reached refuses it with nothing sent
 * (UsageLimitReachedException) - and counted after, failed or not, under
 * the configured name (a limit scoped per key counts per account). A call
 * that sent nothing - a post the network's capabilities refuse, a
 * credential missing, an operation not supported, a wait still running -
 * is not counted; any other failure is (the network may have taken it).
 * The call is made once: the guard never retries, so a post refused, failed
 * or lost on the way is never sent a second time by omnipost.
 */
final class GuardedPublisher implements PublisherInterface
{
    public function __construct(
        private readonly PublisherInterface $provider,
        private readonly PolicyGuard $guard,
        private readonly string $name,
    ) {
    }

    /** The provider itself, unguarded (its token, its OAuth flow). */
    public function provider(): PublisherInterface
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

    public function capabilities(): Capabilities
    {
        return $this->provider->capabilities();
    }

    public function publish(Post $post): Publication
    {
        return $this->run('publish', fn (): Publication => $this->provider->publish($post));
    }

    public function status(string $publicationId): Publication
    {
        return $this->run('status', fn (): Publication => $this->provider->status($publicationId));
    }

    public function delete(string $publicationId): void
    {
        $this->run('delete', function () use ($publicationId): void {
            $this->provider->delete($publicationId);
        });
    }

    /**
     * Checked before, called once, counted after unless nothing was sent.
     *
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
        } catch (InvalidPostException|InvalidConfigException|NotSupportedException|UsageLimitReachedException $e) {
            $sent = false;

            throw $e;
        } finally {
            if ($sent) {
                $this->guard->record($operation, $context);
            }
        }
    }
}
