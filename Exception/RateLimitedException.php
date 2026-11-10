<?php

namespace Omnipost\Exception;

/**
 * The network refused a call it was sent for its rate or its quota (a 429,
 * Meta's codes 4, 17, 32, 613 and 80002, Google's quotaExceeded): a
 * ProviderException, with the wait it asked for when it said one. The wait
 * is kept: the provider's next calls are refused before they are sent
 * (UsageLimitReachedException) until it is over. Nothing retries the call: a
 * post or an upload refused so is the caller's to send again, once.
 */
final class RateLimitedException extends ProviderException
{
    public function __construct(
        string $provider,
        string $message,
        ?int $status = null,
        ?string $providerCode = null,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($provider, $message, $status, $providerCode, $previous);
    }
}
