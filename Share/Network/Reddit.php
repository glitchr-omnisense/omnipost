<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** Reddit's submit page: a link post, its address and its title; the visitor picks the community. */
final class Reddit extends AbstractNetwork
{
    public function name(): string
    {
        return 'reddit';
    }

    public function color(): string
    {
        return '#ff4500';
    }

    public function uses(): array
    {
        return ['url', 'title'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://www.reddit.com/submit?'.self::query(['url' => $shareable->url, 'title' => $shareable->title]);
    }
}
