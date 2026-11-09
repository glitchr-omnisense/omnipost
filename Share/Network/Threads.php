<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** Threads' post intent: the message (with the hashtags) and the address apart, which Threads shows as the link's card. */
final class Threads extends AbstractNetwork
{
    public function name(): string
    {
        return 'threads';
    }

    public function color(): string
    {
        return '#000000';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text', 'hashtags'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        $text = $shareable->message();
        if ([] !== $shareable->hashtags) {
            $text .= ' '.implode(' ', array_map(static fn (string $tag) => '#'.$tag, $shareable->hashtags));
        }

        return 'https://www.threads.com/intent/post?'.self::query(['text' => $text, 'url' => $shareable->url]);
    }
}
