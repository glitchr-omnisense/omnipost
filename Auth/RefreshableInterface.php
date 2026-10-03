<?php

namespace Omnipost\Auth;

use Omnipost\Model\Token;

/** A provider whose token dies: the site asks for a fresh one before it does (a cron). */
interface RefreshableInterface
{
    public function token(): ?Token;

    public function refresh(): Token;
}
