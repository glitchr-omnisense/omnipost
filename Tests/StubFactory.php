<?php

namespace Omnipost\Tests;

use Omnipost\Config;
use Omnipost\FeedInterface;
use Omnipost\Model\Account;
use Omnipost\Model\Capabilities;
use Omnipost\Model\Feed;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Publication;
use Omnipost\Model\PublicationState;
use Omnipost\Platform;
use Omnipost\ProviderFactory;
use Omnipost\ProviderInterface;
use Omnipost\PublisherInterface;

/** A network that takes every reel at once, for the tests: "stub" needs a token. */
final class StubFactory extends ProviderFactory
{
    protected function populate(Config $config): void
    {
        $config->defaults([
            'omnipost.factory_name' => 'stub',
            'omnipost.required_options' => ['token'],
            'handle' => 'stub',
        ]);
    }

    protected function build(Config $config): ProviderInterface
    {
        return new StubPublisher((string) $config['token'], (string) $config['handle']);
    }
}

/** A network that is only read, and needs nothing. */
final class StubWallFactory extends ProviderFactory
{
    protected function populate(Config $config): void
    {
        $config->defaults(['omnipost.factory_name' => 'wall', 'handle' => 'wall']);
    }

    protected function build(Config $config): ProviderInterface
    {
        return new StubWall((string) $config['handle']);
    }
}

final class StubPublisher implements PublisherInterface
{
    public int $published = 0;

    public function __construct(public readonly string $token, public readonly string $handle)
    {
    }

    public function getName(): string
    {
        return 'stub';
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
        return new Publication(Platform::MASTODON, 'post_'.$this->token.'_'.++$this->published, PublicationState::PUBLISHED, 'https://stub.example/'.$this->handle.'/'.$this->published);
    }

    public function status(string $publicationId): Publication
    {
        return new Publication(Platform::MASTODON, $publicationId, PublicationState::PUBLISHED);
    }

    public function delete(string $publicationId): void
    {
    }
}

final class StubWall implements FeedInterface
{
    public function __construct(public readonly string $handle)
    {
    }

    public function getName(): string
    {
        return 'wall';
    }

    public function getPlatform(): Platform
    {
        return Platform::BLUESKY;
    }

    public function account(): Account
    {
        return new Account(Platform::BLUESKY, '1', $this->handle);
    }

    public function feed(?string $cursor = null, int $limit = 25): Feed
    {
        return new Feed([]);
    }
}
