<?php

namespace Omnipost;

/**
 * Reserved: delivering a release to the streaming platforms through a
 * distributor (DDEX). No provider implements it yet - the labels
 * distribute - and the contract is kept narrow until one does: a release
 * (its audio, artwork and metadata) is created, delivered, and its state
 * read back by id.
 */
interface DistributorInterface extends ProviderInterface
{
    /**
     * @param array<string, mixed> $release the distributor's own shape, for now
     *
     * @return string the distributor's id of the release
     */
    public function createRelease(array $release): string;

    public function deliver(string $releaseId): void;

    /** @return array<string, mixed> the distributor's own status shape, for now */
    public function deliveryStatus(string $releaseId): array;
}
