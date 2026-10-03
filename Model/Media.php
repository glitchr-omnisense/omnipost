<?php

namespace Omnipost\Model;

/**
 * A picture or a video to post. The networks fetch it themselves, so $url
 * must be public (a site exposes a signed URL for the time of the
 * publication); $path is the local file, for the providers that upload
 * (YouTube) and for the Validator to measure.
 */
final class Media
{
    public function __construct(
        public readonly MediaKind $kind,
        public readonly string $url,
        public readonly ?string $path = null,
        public readonly ?string $mime = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        /** seconds */
        public readonly ?float $duration = null,
        /** bytes */
        public readonly ?int $size = null,
        /** A still for a video: shown before it plays */
        public readonly ?string $coverUrl = null,
        public readonly ?string $alt = null,
    ) {
    }

    public static function image(string $url, ?string $path = null, ?int $width = null, ?int $height = null, ?string $alt = null): self
    {
        return new self(MediaKind::IMAGE, $url, $path, null, $width, $height, null, null, null, $alt);
    }

    public static function video(string $url, ?string $path = null, ?float $duration = null, ?int $width = null, ?int $height = null, ?string $coverUrl = null): self
    {
        return new self(MediaKind::VIDEO, $url, $path, null, $width, $height, $duration, null, $coverUrl);
    }

    /** "9:16", "1:1", "16:9"... null when the size is unknown. */
    public function aspectRatio(): ?string
    {
        if (!$this->width || !$this->height) {
            return null;
        }
        $gcd = static function (int $a, int $b) use (&$gcd): int { return $b ? $gcd($b, $a % $b) : $a; };
        $g = $gcd($this->width, $this->height);

        return ($this->width / $g).':'.($this->height / $g);
    }

    /** width / height, null when unknown. */
    public function ratio(): ?float
    {
        return $this->width && $this->height ? $this->width / $this->height : null;
    }
}
