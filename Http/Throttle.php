<?php

namespace Omnipost\Http;

use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\UsageLimitReached;

/**
 * A provider's API client and the waits its network asks for
 * (omnisense/contracts' Backoff, a factory's Config::backoff()): before()
 * refuses a call while a wait lasts (UsageLimitReachedException: nothing
 * sent), after() reads each answer's Retry-After and RateLimit fields and
 * keeps the wait they ask, giving it back for the provider's own error
 * (a RateLimitedException, a ProviderException as before). Nothing here
 * retries: a post or an upload refused is never sent again by omnipost.
 */
final class Throttle
{
    /** @throws \Omnipost\Exception\UsageLimitReachedException while the provider's wait lasts, its trace without arguments */
    public static function before(?Backoff $backoff): void
    {
        try {
            $backoff?->clear();
        } catch (UsageLimitReached $e) {
            throw Redaction::withoutArguments($e);
        }
    }

    /**
     * The wait an answer asks for, in seconds (null when it asks none),
     * kept by the Backoff so that the next calls wait too. Never throws.
     *
     * @param array<string, list<string>> $headers the answer's
     */
    public static function after(?Backoff $backoff, int $status, array $headers): ?int
    {
        if (null === $backoff) {
            return null;
        }
        try {
            $backoff->observe($status, $headers);
        } catch (UsageLimitReached $e) {
            return $e->retryAfter;
        }

        return null;
    }

    /**
     * No call for $seconds, on a signal the provider read itself (Meta's
     * estimated_time_to_regain_access): the wait kept, as a Retry-After's.
     */
    public static function hold(?Backoff $backoff, int $seconds, string $reason): void
    {
        if (null !== $backoff && $seconds > 0) {
            $backoff->hold($seconds, $reason);
        }
    }
}
