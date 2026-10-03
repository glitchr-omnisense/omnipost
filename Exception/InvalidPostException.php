<?php

namespace Omnipost\Exception;

use Omnipost\Model\Violation;

/** The post breaks the network's capabilities: nothing was sent. */
final class InvalidPostException extends \InvalidArgumentException implements OmnipostException
{
    /** @param list<Violation> $violations */
    public function __construct(public readonly string $provider, public readonly array $violations)
    {
        parent::__construct(\sprintf('[%s] %s', $provider, implode('; ', array_map('strval', $violations))));
    }
}
