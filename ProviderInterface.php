<?php

namespace Omnipost;

/**
 * A network's provider. It implements PublisherInterface and/or
 * FeedInterface, and Auth\RefreshableInterface when its token dies.
 */
interface ProviderInterface
{
    /** The name it is configured as: "instagram", "youtube"... */
    public function getName(): string;

    public function getPlatform(): Platform;
}
