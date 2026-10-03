<?php

namespace Omnipost\Exception;

/** The network refused, or could not be reached. */
class ProviderException extends \RuntimeException implements OmnipostException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        public readonly ?int $status = null,
        /** The network's own error code, when it gave one */
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('[%s] %s', $provider, $message), 0, $previous);
    }
}
