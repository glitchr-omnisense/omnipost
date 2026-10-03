<?php

namespace Omnipost\Model;

use Omnipost\Platform;

/**
 * The canonical post - one reel (or picture, carousel), one caption, its
 * hashtags, a title for the networks that want one - and its variants per
 * network. for(Platform) gives the post as that network receives it.
 */
final class Post
{
    /**
     * @param list<Media>             $media
     * @param list<string>            $tags     hashtags without the '#'
     * @param array<string, Variant>  $variants platform value => variant
     * @param array<string, mixed>    $options  provider-specific extras (a variant's win)
     */
    public function __construct(
        public readonly PostKind $kind,
        public readonly array $media,
        public readonly string $caption = '',
        public readonly ?string $title = null,
        public readonly array $tags = [],
        public readonly ?string $coverUrl = null,
        public readonly ?\DateTimeImmutable $scheduledAt = null,
        public readonly array $variants = [],
        public readonly array $options = [],
        /** A link to carry (the site's page): networks that take one put it where they can */
        public readonly ?string $link = null,
    ) {
    }

    public static function reel(Media $video, string $caption = '', ?string $title = null, array $tags = []): self
    {
        return new self(PostKind::REEL, [$video], $caption, $title, $tags, $video->coverUrl);
    }

    public static function image(Media $image, string $caption = '', array $tags = []): self
    {
        return new self(PostKind::IMAGE, [$image], $caption, null, $tags);
    }

    /** @param list<Media> $media */
    public static function carousel(array $media, string $caption = '', array $tags = []): self
    {
        return new self(PostKind::CAROUSEL, $media, $caption, null, $tags);
    }

    public function withVariant(Variant $variant): self
    {
        return new self($this->kind, $this->media, $this->caption, $this->title, $this->tags, $this->coverUrl, $this->scheduledAt, [$variant->platform->value => $variant] + $this->variants, $this->options, $this->link);
    }

    public function withScheduledAt(?\DateTimeImmutable $at): self
    {
        return new self($this->kind, $this->media, $this->caption, $this->title, $this->tags, $this->coverUrl, $at, $this->variants, $this->options, $this->link);
    }

    public function variant(Platform $platform): ?Variant
    {
        return $this->variants[$platform->value] ?? null;
    }

    /** The post as $platform receives it: the variant's fields over the canonical ones, no variants left. */
    public function for(Platform $platform): self
    {
        $v = $this->variant($platform);
        if (null === $v) {
            return new self($this->kind, $this->media, $this->caption, $this->title, $this->tags, $this->coverUrl, $this->scheduledAt, [], $this->options, $this->link);
        }

        return new self(
            $this->kind,
            $v->media ?? $this->media,
            $v->caption ?? $this->caption,
            $v->title ?? $this->title,
            $v->tags ?? $this->tags,
            $v->coverUrl ?? $this->coverUrl,
            $this->scheduledAt,
            [],
            $v->options + $this->options,
            $this->link,
        );
    }

    /** The caption with the hashtags appended, as most networks want it. */
    public function text(): string
    {
        $tags = array_map(static fn (string $t) => '#'.ltrim($t, '#'), $this->tags);

        return trim($this->caption.($tags ? "\n\n".implode(' ', $tags) : ''));
    }

    public function video(): ?Media
    {
        foreach ($this->media as $m) {
            if (MediaKind::VIDEO === $m->kind) {
                return $m;
            }
        }

        return null;
    }
}
