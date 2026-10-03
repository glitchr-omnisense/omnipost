<?php

namespace Omnipost\Model;

/**
 * What a network accepts, for the Validator to check a post before it is
 * sent: the kinds, the video's bounds, the ratios, the caption's length,
 * the hashtags, whether a title is required.
 *
 * @param list<PostKind> $kinds
 * @param list<string>   $aspectRatios "9:16", "1:1", "4:5", "16:9"... empty: any
 * @param list<string>   $videoFormats mime types or extensions
 * @param list<string>   $imageFormats
 */
final class Capabilities
{
    public function __construct(
        public readonly array $kinds,
        public readonly ?float $minDuration = null,
        public readonly ?float $maxDuration = null,
        public readonly array $aspectRatios = [],
        /** tolerance on the ratio, as a fraction (0.01 = 1 %) */
        public readonly float $ratioTolerance = 0.01,
        /** bytes */
        public readonly ?int $maxVideoSize = null,
        public readonly ?int $maxImageSize = null,
        public readonly ?int $captionMaxLength = null,
        public readonly ?int $maxTags = null,
        public readonly bool $titleRequired = false,
        public readonly ?int $titleMaxLength = null,
        public readonly int $mediaMaxCount = 1,
        public readonly array $videoFormats = [],
        public readonly array $imageFormats = [],
        public readonly ?int $minWidth = null,
        public readonly ?int $minHeight = null,
        public readonly bool $scheduling = false,
        public readonly bool $linkInCaption = true,
    ) {
    }

    public function supports(PostKind $kind): bool
    {
        return \in_array($kind, $this->kinds, true);
    }

    /** Whether $ratio (width / height) is one of the accepted ratios, within the tolerance. */
    public function acceptsRatio(float $ratio): bool
    {
        if (!$this->aspectRatios) {
            return true;
        }
        foreach ($this->aspectRatios as $accepted) {
            [$w, $h] = array_map('floatval', explode(':', $accepted) + [1 => 1]);
            if ($h > 0 && abs($ratio - $w / $h) <= $this->ratioTolerance * ($w / $h)) {
                return true;
            }
        }

        return false;
    }
}
