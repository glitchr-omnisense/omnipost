<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** Pinterest's pin: the address, the picture pinned and its description; without a picture Pinterest offers the page's own. */
final class Pinterest extends AbstractNetwork
{
    public function name(): string
    {
        return 'pinterest';
    }

    public function color(): string
    {
        return '#e60023';
    }

    public function uses(): array
    {
        return ['url', 'image', 'title', 'text'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://pinterest.com/pin/create/button/?'.self::query([
            'url' => $shareable->url,
            'media' => $shareable->image,
            'description' => $shareable->message(),
        ]);
    }
}
