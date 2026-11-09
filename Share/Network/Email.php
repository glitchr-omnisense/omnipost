<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/** An e-mail from the visitor's own mail client: the title as its subject, the text (when there is one) and the address as its body. */
final class Email extends AbstractNetwork
{
    public function name(): string
    {
        return 'email';
    }

    public function icon(): string
    {
        return 'envelope';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        $body = null !== $shareable->text && '' !== $shareable->text ? $shareable->text."\n\n".$shareable->url : $shareable->url;

        return 'mailto:?'.self::query(['subject' => $shareable->title, 'body' => $body]);
    }
}
