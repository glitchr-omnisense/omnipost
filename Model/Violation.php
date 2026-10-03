<?php

namespace Omnipost\Model;

/** One thing a network would refuse: where (caption, media[0].duration...) and why, readable. */
final class Violation
{
    public function __construct(
        public readonly string $path,
        public readonly string $message,
    ) {
    }

    public function __toString(): string
    {
        return $this->path.': '.$this->message;
    }
}
