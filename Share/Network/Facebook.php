<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** Facebook's sharer: the address alone; Facebook reads the title and the picture from the page's og:* tags. */
final class Facebook extends AbstractNetwork
{
    public function name(): string
    {
        return 'facebook';
    }

    public function color(): string
    {
        return '#1877f2';
    }

    public function uses(): array
    {
        return ['url'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://www.facebook.com/sharer/sharer.php?'.self::query(['u' => $shareable->url]);
    }
}
