<?php

namespace Omnipost\Exception;

/** A provider misconfigured: a credential missing, an unknown factory (a LogicException, as before; omnisense/contracts' InvalidConfigException). */
final class InvalidConfigException extends \Omnisense\Contracts\Exception\InvalidConfigException implements OmnipostException
{
}
