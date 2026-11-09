<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** LinkedIn's share-offsite: the address alone (it no longer takes a title nor a summary); LinkedIn reads the page's og:* tags. */
final class LinkedIn extends AbstractNetwork
{
    public function name(): string
    {
        return 'linkedin';
    }

    public function color(): string
    {
        return '#0a66c2';
    }

    public function uses(): array
    {
        return ['url'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://www.linkedin.com/sharing/share-offsite/?'.self::query(['url' => $shareable->url]);
    }
}
