<?php

namespace Omnipost;

/** The networks a post may go to. */
enum Platform: string
{
    case INSTAGRAM = 'instagram';
    case YOUTUBE = 'youtube';
    case FACEBOOK = 'facebook';
    case TIKTOK = 'tiktok';
    case THREADS = 'threads';
    case X = 'x';
    case LINKEDIN = 'linkedin';
    case BLUESKY = 'bluesky';
    case MASTODON = 'mastodon';
    case PINTEREST = 'pinterest';

    public function label(): string
    {
        return match ($this) {
            self::INSTAGRAM => 'Instagram', self::YOUTUBE => 'YouTube', self::FACEBOOK => 'Facebook', self::TIKTOK => 'TikTok',
            self::THREADS => 'Threads', self::X => 'X', self::LINKEDIN => 'LinkedIn', self::BLUESKY => 'Bluesky',
            self::MASTODON => 'Mastodon', self::PINTEREST => 'Pinterest',
        };
    }
}
