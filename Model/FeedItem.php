<?php

namespace Omnipost\Model;

/**
 * One of the account's own posts, as the network lists it: a picture, a
 * video (its thumbnail), a carousel (its children). What a site caches
 * to show a wall.
 *
 * @param list<FeedItem> $children
 */
final class FeedItem
{
    public function __construct(
        public readonly string $id,
        public readonly PostKind $kind,
        public readonly string $permalink,
        public readonly ?string $url = null,
        public readonly ?string $thumbnailUrl = null,
        public readonly ?string $caption = null,
        public readonly ?\DateTimeImmutable $publishedAt = null,
        public readonly ?float $duration = null,
        public readonly array $children = [],
        public readonly ?int $likes = null,
        public readonly ?int $comments = null,
        public readonly ?int $views = null,
        public readonly array $raw = [],
    ) {
    }
}
