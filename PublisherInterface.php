<?php

namespace Omnipost;

use Omnipost\Model\Capabilities;
use Omnipost\Model\Post;
use Omnipost\Model\Publication;

/**
 * Publishing on one network. publish() hands the post over and answers
 * with the network's id and a state that may not be final yet (Instagram
 * processes a reel for a while): status() reads it again. A post that
 * breaks capabilities() is refused before anything is sent
 * (InvalidPostException).
 */
interface PublisherInterface extends ProviderInterface
{
    public function capabilities(): Capabilities;

    public function publish(Post $post): Publication;

    public function status(string $publicationId): Publication;

    /** Takes the post down; NotSupportedException where the network's API cannot. */
    public function delete(string $publicationId): void;
}
