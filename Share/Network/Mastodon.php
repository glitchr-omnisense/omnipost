<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/**
 * Mastodon's share page, on one instance: one text, the message, the hashtags
 * and the address. Mastodon has no central site: the instance is configured,
 * or - when none is - the visitor gives theirs (omnishare's script asks it on
 * a click, action "instance"; without the script the link goes to
 * mastodon.social).
 */
final class Mastodon extends AbstractNetwork
{
    public const FALLBACK = 'mastodon.social';

    private ?string $instance;

    /** @param string|null $instance a host ("mastodon.social", "piaille.fr"), or an address whose host is taken */
    public function __construct(?string $instance = null)
    {
        if (null !== $instance) {
            $instance = trim($instance);
            $host = parse_url(str_contains($instance, '://') ? $instance : 'https://'.$instance, \PHP_URL_HOST);
            $instance = \is_string($host) && '' !== $host ? strtolower($host) : null;
        }
        $this->instance = $instance;
    }

    public function name(): string
    {
        return 'mastodon';
    }

    public function color(): string
    {
        return '#6364ff';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text', 'hashtags'];
    }

    /** The instance configured, null when the visitor gives theirs. */
    public function instance(): ?string
    {
        return $this->instance;
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://'.($this->instance ?? self::FALLBACK).'/share?'.self::query(['text' => self::post($shareable)]);
    }

    public function action(): ?string
    {
        return null === $this->instance ? 'instance' : null;
    }
}
