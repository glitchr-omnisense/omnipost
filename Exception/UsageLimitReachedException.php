<?php

namespace Omnipost\Exception;

use Omnisense\Contracts\Policy\UsageLimitReached;

/**
 * A call refused before anything was sent: a limit of the provider's usage
 * policy is reached (100 published posts a day on Instagram, YouTube's upload
 * calls), or the network asked to wait and the wait lasts. $retryAfter says
 * in how many seconds to try again, when it is known.
 */
final class UsageLimitReachedException extends UsageLimitReached implements OmnipostException
{
}
