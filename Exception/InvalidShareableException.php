<?php

namespace Omnipost\Exception;

/** A share link's Shareable (Share\Shareable) given an address that is not whole (no http(s) scheme or no host). */
final class InvalidShareableException extends \InvalidArgumentException implements OmnipostException
{
}
