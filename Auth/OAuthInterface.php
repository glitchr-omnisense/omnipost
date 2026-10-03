<?php

namespace Omnipost\Auth;

use Omnipost\Model\Token;

/** Connecting the account from a back office: the URL to send the user to, the code exchanged on the way back. */
interface OAuthInterface
{
    /** @param list<string> $scopes */
    public function authorizationUrl(string $redirectUri, string $state, array $scopes = []): string;

    public function exchange(string $code, string $redirectUri): Token;
}
