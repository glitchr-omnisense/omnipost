<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** Telegram's share page: the address and the message apart; the visitor picks the chat. */
final class Telegram extends AbstractNetwork
{
    public function name(): string
    {
        return 'telegram';
    }

    public function color(): string
    {
        return '#26a5e4';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://t.me/share/url?'.self::query(['url' => $shareable->url, 'text' => $shareable->message()]);
    }
}
