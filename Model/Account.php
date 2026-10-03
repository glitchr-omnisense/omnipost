<?php

namespace Omnipost\Model;

use Omnipost\Platform;

/** The connected account: who the posts go out as. */
final class Account
{
    public function __construct(
        public readonly Platform $platform,
        public readonly string $id,
        public readonly string $username,
        public readonly ?string $name = null,
        public readonly ?string $url = null,
        public readonly ?string $pictureUrl = null,
        public readonly ?int $followers = null,
        public readonly ?int $posts = null,
        public readonly ?string $kind = null,
    ) {
    }
}
