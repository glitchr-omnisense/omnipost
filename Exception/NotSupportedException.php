<?php

namespace Omnipost\Exception;

/** The provider does not do that (no feed, no deletion, no scheduling...). */
final class NotSupportedException extends \LogicException implements OmnipostException
{
    public static function operation(string $provider, string $operation): self
    {
        return new self(\sprintf('The "%s" provider does not %s.', $provider, $operation));
    }
}
