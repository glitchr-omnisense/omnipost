<?php

namespace Omnishare\Exception;

/** A Shareable given an address that is not whole (no http(s) scheme or no host). */
final class InvalidShareableException extends \InvalidArgumentException implements OmnishareException
{
}
