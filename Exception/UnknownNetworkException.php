<?php

namespace Omnipost\Exception;

/** A share network asked of a Share\Registry by a name it does not hold. */
final class UnknownNetworkException extends \OutOfBoundsException implements OmnipostException
{
}
