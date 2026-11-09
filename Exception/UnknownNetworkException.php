<?php

namespace Omnishare\Exception;

/** A network asked of a Registry by a name it does not hold. */
final class UnknownNetworkException extends \OutOfBoundsException implements OmnishareException
{
}
