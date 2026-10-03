<?php

namespace Omnipost;

use Omnipost\Model\Capabilities;
use Omnipost\Model\MediaKind;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Violation;

/**
 * A post against a network's capabilities, before anything is sent: the
 * kind, the number of media, the video's length and ratio, the caption's
 * length, the hashtags, the title. Readable violations, by field, for a
 * back office to show.
 */
final class Validator
{
    /** @return list<Violation> */
    public function validate(Post $post, Capabilities $c): array
    {
        $v = [];
        if (!$c->supports($post->kind)) {
            $v[] = new Violation('kind', \sprintf('%s posts are not accepted (accepted: %s).', $post->kind->value, implode(', ', array_map(static fn (PostKind $k) => $k->value, $c->kinds))));
        }
        if (!$post->media) {
            $v[] = new Violation('media', 'No media.');
        } elseif (\count($post->media) > $c->mediaMaxCount) {
            $v[] = new Violation('media', \sprintf('%d media, at most %d.', \count($post->media), $c->mediaMaxCount));
        }
        foreach ($post->media as $i => $m) {
            $path = "media[$i]";
            if (MediaKind::VIDEO === $m->kind) {
                if (null !== $m->duration && null !== $c->minDuration && $m->duration < $c->minDuration) {
                    $v[] = new Violation("$path.duration", \sprintf('%.1f s, at least %.0f s.', $m->duration, $c->minDuration));
                }
                if (null !== $m->duration && null !== $c->maxDuration && $m->duration > $c->maxDuration) {
                    $v[] = new Violation("$path.duration", \sprintf('%.1f s, at most %.0f s.', $m->duration, $c->maxDuration));
                }
                if (null !== $m->size && null !== $c->maxVideoSize && $m->size > $c->maxVideoSize) {
                    $v[] = new Violation("$path.size", \sprintf('%s, at most %s.', self::bytes($m->size), self::bytes($c->maxVideoSize)));
                }
                if ($c->videoFormats && $m->mime && !self::format($m->mime, $m->url, $c->videoFormats)) {
                    $v[] = new Violation("$path.format", \sprintf('%s is not accepted (accepted: %s).', $m->mime, implode(', ', $c->videoFormats)));
                }
            } else {
                if (null !== $m->size && null !== $c->maxImageSize && $m->size > $c->maxImageSize) {
                    $v[] = new Violation("$path.size", \sprintf('%s, at most %s.', self::bytes($m->size), self::bytes($c->maxImageSize)));
                }
                if ($c->imageFormats && $m->mime && !self::format($m->mime, $m->url, $c->imageFormats)) {
                    $v[] = new Violation("$path.format", \sprintf('%s is not accepted (accepted: %s).', $m->mime, implode(', ', $c->imageFormats)));
                }
            }
            if (null !== ($ratio = $m->ratio()) && !$c->acceptsRatio($ratio)) {
                $v[] = new Violation("$path.ratio", \sprintf('%s is not accepted (accepted: %s).', $m->aspectRatio(), implode(', ', $c->aspectRatios)));
            }
            if ($c->minWidth && $m->width && $m->width < $c->minWidth) {
                $v[] = new Violation("$path.width", \sprintf('%d px wide, at least %d.', $m->width, $c->minWidth));
            }
            if ($c->minHeight && $m->height && $m->height < $c->minHeight) {
                $v[] = new Violation("$path.height", \sprintf('%d px high, at least %d.', $m->height, $c->minHeight));
            }
        }
        $text = $post->text();
        if (null !== $c->captionMaxLength && mb_strlen($text) > $c->captionMaxLength) {
            $v[] = new Violation('caption', \sprintf('%d characters with the hashtags, at most %d.', mb_strlen($text), $c->captionMaxLength));
        }
        if (null !== $c->maxTags && \count($post->tags) > $c->maxTags) {
            $v[] = new Violation('tags', \sprintf('%d hashtags, at most %d.', \count($post->tags), $c->maxTags));
        }
        if ($c->titleRequired && '' === trim((string) $post->title)) {
            $v[] = new Violation('title', 'A title is required.');
        }
        if (null !== $c->titleMaxLength && mb_strlen((string) $post->title) > $c->titleMaxLength) {
            $v[] = new Violation('title', \sprintf('%d characters, at most %d.', mb_strlen((string) $post->title), $c->titleMaxLength));
        }
        if ($post->scheduledAt && !$c->scheduling) {
            $v[] = new Violation('scheduledAt', 'Scheduling is not offered: the site must post it at that time itself.');
        }

        return $v;
    }

    /** @param list<string> $accepted mime types ("video/mp4") or extensions ("mp4") */
    private static function format(string $mime, string $url, array $accepted): bool
    {
        $ext = strtolower(pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION));
        foreach ($accepted as $a) {
            if (0 === strcasecmp($a, $mime) || ('' !== $ext && 0 === strcasecmp(ltrim($a, '.'), $ext))) {
                return true;
            }
        }

        return false;
    }

    private static function bytes(int $n): string
    {
        return $n >= 1 << 30 ? round($n / (1 << 30), 1).' GB' : ($n >= 1 << 20 ? round($n / (1 << 20)).' MB' : round($n / 1024).' kB');
    }
}
