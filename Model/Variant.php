<?php

namespace Omnipost\Model;

use Omnipost\Platform;

/**
 * What one network gets instead of the canonical post: its own caption
 * (shorter, other hashtags), a title (YouTube), another cover, another
 * rendering of the video (a template of its own). Null keeps the post's.
 *
 * @param list<Media>|null  $media
 * @param list<string>|null $tags
 */
final class Variant
{
    public function __construct(
        public readonly Platform $platform,
        public readonly ?string $caption = null,
        public readonly ?string $title = null,
        public readonly ?array $tags = null,
        public readonly ?array $media = null,
        public readonly ?string $coverUrl = null,
        /** Provider-specific extras: YouTube's privacy, Instagram's share_to_feed... */
        public readonly array $options = [],
    ) {
    }
}
