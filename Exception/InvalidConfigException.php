<?php

namespace Omnipost\Exception;

/** A provider misconfigured: a credential missing, an unknown factory. */
final class InvalidConfigException extends \LogicException implements OmnipostException
{
}
