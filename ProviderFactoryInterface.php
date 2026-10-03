<?php

namespace Omnipost;

/** Builds a provider from its options (a token, an app id, a channel...). */
interface ProviderFactoryInterface
{
    /** The name providers are configured with: "instagram", "youtube"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
