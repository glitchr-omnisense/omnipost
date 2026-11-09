<?php

namespace Omnishare\Network;

use Omnishare\NetworkInterface;
use Omnishare\Shareable;

/**
 * What most networks share: the label key and the icon named after the
 * network, no brand colour, a plain link; and the query string they build.
 */
abstract class AbstractNetwork implements NetworkInterface
{
    public function labelKey(): string
    {
        return 'omnishare.'.$this->name();
    }

    public function icon(): string
    {
        return $this->name();
    }

    public function color(): ?string
    {
        return null;
    }

    public function action(): ?string
    {
        return null;
    }

    /**
     * A query string encoded as RFC 3986 says (rawurlencode: a space is %20,
     * never "+", which a mail client shows as it is in a mailto:), the
     * parameters in the order given, the null and empty ones left out.
     *
     * @param array<string, ?string> $parameters
     */
    protected static function query(array $parameters): string
    {
        return http_build_query(array_filter($parameters, static fn (?string $value) => null !== $value && '' !== $value), '', '&', \PHP_QUERY_RFC3986);
    }

    /** The message, the hashtags as "#tag" and the address, one after the other: for the networks that take a single text. */
    protected static function post(Shareable $shareable, bool $hashtags = true): string
    {
        $parts = [$shareable->message()];
        if ($hashtags && [] !== $shareable->hashtags) {
            $parts[] = implode(' ', array_map(static fn (string $tag) => '#'.$tag, $shareable->hashtags));
        }
        $parts[] = $shareable->url;

        return implode(' ', $parts);
    }
}
