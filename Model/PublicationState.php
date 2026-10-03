<?php

namespace Omnipost\Model;

enum PublicationState: string
{
    /** Handed to the network, not yet processed (Instagram's container, a scheduled Short) */
    case PENDING = 'pending';
    /** The network is working on it (transcoding) */
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';

    public function isFinal(): bool
    {
        return self::PUBLISHED === $this || self::FAILED === $this;
    }
}
