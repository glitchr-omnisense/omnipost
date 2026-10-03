<?php

namespace Omnipost\Model;

use Omnipost\Platform;

/**
 * What became of a post on one network: the network's id for it, its
 * state, its permalink once published, the error otherwise. A provider's
 * status() reads it again by $id until the state is final.
 */
final class Publication
{
    public function __construct(
        public readonly Platform $platform,
        public readonly string $id,
        public readonly PublicationState $state,
        public readonly ?string $permalink = null,
        public readonly ?string $error = null,
        public readonly ?\DateTimeImmutable $publishedAt = null,
        /** The network's own answer, for the record */
        public readonly array $raw = [],
    ) {
    }

    public function isPublished(): bool
    {
        return PublicationState::PUBLISHED === $this->state;
    }

    public function isFailed(): bool
    {
        return PublicationState::FAILED === $this->state;
    }
}
