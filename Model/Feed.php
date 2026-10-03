<?php

namespace Omnipost\Model;

/** A page of the account's posts, newest first, and the cursor of the next page. */
final class Feed
{
    /** @param list<FeedItem> $items */
    public function __construct(
        public readonly array $items,
        public readonly ?string $next = null,
    ) {
    }
}
