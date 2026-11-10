<?php

namespace Omnipost\Exception;

use Omnisense\Contracts\Exception\ExceptionInterface;

/** Every exception Omnipost throws (omnisense/contracts' ExceptionInterface, so a catch of the kernel's catches it too). */
interface OmnipostException extends ExceptionInterface
{
}
