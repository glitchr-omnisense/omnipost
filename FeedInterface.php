<?php

namespace Omnipost;

use Omnipost\Model\Account;
use Omnipost\Model\Feed;

/** Reading one's own account: who it is, what it posted. */
interface FeedInterface extends ProviderInterface
{
    public function account(): Account;

    /** Newest first; $cursor from the previous page's $next. */
    public function feed(?string $cursor = null, int $limit = 25): Feed;
}
