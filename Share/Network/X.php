<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/**
 * X's (Twitter's) post intent: the address, the message, the hashtags and the
 * account to credit. twitter.com's intent, which x.com serves the same.
 */
final class X extends AbstractNetwork
{
    public function name(): string
    {
        return 'x';
    }

    public function color(): string
    {
        return '#000000';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text', 'hashtags', 'via'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://twitter.com/intent/tweet?'.self::query([
            'url' => $shareable->url,
            'text' => $shareable->message(),
            'hashtags' => implode(',', $shareable->hashtags),
            'via' => $shareable->via,
        ]);
    }
}
