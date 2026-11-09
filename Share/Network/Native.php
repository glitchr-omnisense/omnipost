<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/**
 * The device's own share sheet (navigator.share: the title, the text and the
 * address; omnishare's script, action "share"), hidden where the browser has
 * none. Its link is the page's address.
 */
final class Native extends AbstractNetwork
{
    public function name(): string
    {
        return 'native';
    }

    public function icon(): string
    {
        return 'share';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return $shareable->url;
    }

    public function action(): string
    {
        return 'share';
    }
}
