<?php

namespace Omnipost\Share\Network;

use Omnipost\Share\Shareable;

/**
 * The page's address copied to the clipboard (the share script, action
 * "copy"; hidden where the browser has no clipboard). Its link is the address
 * itself, so a visitor without the script can still copy it from the link.
 */
final class CopyLink extends AbstractNetwork
{
    public function name(): string
    {
        return 'copy';
    }

    public function icon(): string
    {
        return 'link';
    }

    public function uses(): array
    {
        return ['url'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return $shareable->url;
    }

    public function action(): string
    {
        return 'copy';
    }
}
