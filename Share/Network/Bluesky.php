<?php

namespace Omnishare\Network;

use Omnishare\Shareable;

/**
 * Bluesky's compose intent: one text, the message, the hashtags and the address; Bluesky draws the
 * link's card from it. Its app opens the composer for a visitor signed in at bsky.app and drops the
 * intent for one signed out (social-app's useComposeIntent: nothing kept for after the sign-in).
 */
final class Bluesky extends AbstractNetwork
{
    public function name(): string
    {
        return 'bluesky';
    }

    public function color(): string
    {
        return '#1185fe';
    }

    public function uses(): array
    {
        return ['url', 'title', 'text', 'hashtags'];
    }

    public function shareUrl(Shareable $shareable): string
    {
        return 'https://bsky.app/intent/compose?'.self::query(['text' => self::post($shareable)]);
    }
}
