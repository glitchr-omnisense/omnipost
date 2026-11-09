<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** WhatsApp's click-to-chat: one text, the message and the address; the visitor picks the chat. */
final class WhatsApp extends AbstractNetwork
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function color(): string
    {
        return '#25d366';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://wa.me/?'.self::query(['text' => self::post($shareable, hashtags: false)]);
    }
}
