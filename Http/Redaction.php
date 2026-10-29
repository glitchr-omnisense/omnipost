<?php

namespace Omnipost\Http;

/**
 * What a failed call may carry of its credentials, taken out before the
 * failure leaves the package: an HTTP client's exception quotes the URL
 * called (a key in its query, a password in its user part), and every
 * exception's trace keeps the arguments of the calls under it (the request
 * options, headers and query with the key) unless PHP runs with
 * zend.exception_ignore_args.
 *
 * copy() gives the previous exception to wrap instead of the client's own:
 * the same class and code, its message with the key's parameters, the user
 * part and every secret value replaced by ***, its trace without arguments,
 * its own previous copied the same way. Properties of the client's class
 * beyond those of \Exception are not copied (a Symfony HttpException's
 * response, a PSR-18 exception's request: their URL holds the key).
 * withoutArguments() takes the arguments out of the trace of the exception
 * thrown in its place.
 */
final class Redaction
{
    public const MASK = '***';

    /** A parameter, header or field named so holds a credential. */
    private const SENSITIVE = '~(^|[^a-z])(api_?key|apikey|key|token|secret|password|passwd|pwd|signature|sig|auth|authorization|credentials?|session|cookie)($|[^a-z])|(apikey|token|secret|password)$~';

    /**
     * The secrets a request carries: the values of its query parameters,
     * headers and body fields named as credentials (key, token, secret,
     * password, signature, auth...), a bearer token, a basic password, the
     * URL's user part.
     *
     * @param array<array-key, mixed> $options the HTTP client's options
     *
     * @return list<string>
     */
    public static function secrets(string $url, array $options = []): array
    {
        $secrets = [];
        $parts = parse_url($url);
        if (false !== $parts) {
            foreach (['user', 'pass'] as $part) {
                $secrets[] = $parts[$part] ?? '';
            }
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
                self::collect($query, $secrets, false);
            }
        }
        foreach (['query', 'body', 'json'] as $option) {
            if (\is_array($options[$option] ?? null)) {
                self::collect($options[$option], $secrets, false);
            }
        }
        if (\is_string($options['body'] ?? null)) {
            parse_str($options['body'], $body);
            self::collect($body, $secrets, false);
        }
        foreach ((array) ($options['headers'] ?? []) as $name => $value) {
            if (\is_int($name) && \is_string($value) && str_contains($value, ':')) {
                [$name, $value] = explode(':', $value, 2);
            }
            if (\is_string($name) && self::sensitive($name)) {
                foreach ((array) $value as $one) {
                    self::credential($one, $secrets);
                }
            }
        }
        foreach (['auth_bearer', 'auth_basic', 'auth_ntlm'] as $option) {
            foreach ((array) ($options[$option] ?? []) as $one) {
                self::credential($one, $secrets);
            }
        }

        return self::unique($secrets);
    }

    /** Whether a parameter, header or field of this name holds a credential. */
    public static function sensitive(string $name): bool
    {
        return 1 === preg_match(self::SENSITIVE, strtolower($name));
    }

    /**
     * A text without credentials: in every address, the user part and the
     * parameters named as credentials masked (api_key=***); then every
     * secret given, raw or URL-encoded, masked wherever it stands.
     *
     * @param list<string> $secrets
     */
    public static function text(string $text, array $secrets = []): string
    {
        $text = (string) preg_replace('~(://)[^/\s@"\'<>]+@~', '$1'.self::MASK.'@', $text);
        $text = (string) preg_replace_callback('~([?&;])([^=&#\s"\'<>]+)=([^&#\s"\'<>]*)~', static fn (array $match): string => self::sensitive(rawurldecode($match[2])) ? $match[1].$match[2].'='.self::MASK : $match[0], $text);
        foreach (self::unique($secrets) as $secret) {
            $text = str_replace(array_unique([$secret, rawurlencode($secret), urlencode($secret)]), self::MASK, $text);
        }

        return $text;
    }

    /**
     * A copy of a failure to wrap in its place: its class, code, file and
     * line, its message through text(), its trace without arguments, its
     * previous copied the same way.
     *
     * @param list<string> $secrets
     */
    public static function copy(\Throwable $failure, array $secrets = []): \Throwable
    {
        $base = $failure instanceof \Exception ? \Exception::class : \Error::class;
        $previous = $failure->getPrevious();
        $previous = null !== $previous ? self::copy($previous, $secrets) : null;
        try {
            $copy = (new \ReflectionClass($failure))->newInstanceWithoutConstructor();
        } catch (\ReflectionException) {
            // An internal final class: a plain exception that names it.
            return self::withoutArguments(new \RuntimeException($failure::class.': '.self::text($failure->getMessage(), $secrets), (int) $failure->getCode(), $previous));
        }
        foreach (['message' => self::text($failure->getMessage(), $secrets), 'code' => $failure->getCode(), 'file' => $failure->getFile(), 'line' => $failure->getLine(), 'trace' => self::argumentless($failure->getTrace()), 'previous' => $previous] as $property => $value) {
            (new \ReflectionProperty($base, $property))->setValue($copy, $value);
        }

        return $copy;
    }

    /**
     * The failure given, the arguments out of its trace (and out of its
     * previous ones'), as zend.exception_ignore_args would have left it.
     *
     * @template T of \Throwable
     *
     * @param T $failure
     *
     * @return T
     */
    public static function withoutArguments(\Throwable $failure): \Throwable
    {
        for ($one = $failure; null !== $one; $one = $one->getPrevious()) {
            (new \ReflectionProperty($one instanceof \Exception ? \Exception::class : \Error::class, 'trace'))->setValue($one, self::argumentless($one->getTrace()));
        }

        return $failure;
    }

    /**
     * @param list<array<string, mixed>> $trace
     *
     * @return list<array<string, mixed>>
     */
    private static function argumentless(array $trace): array
    {
        return array_map(static function (array $frame): array {
            unset($frame['args']);

            return $frame;
        }, $trace);
    }

    /**
     * @param array<array-key, mixed> $fields
     * @param list<string>            $secrets
     */
    private static function collect(array $fields, array &$secrets, bool $sensitive): void
    {
        foreach ($fields as $name => $value) {
            $named = $sensitive || (\is_string($name) && self::sensitive($name));
            if (\is_array($value)) {
                self::collect($value, $secrets, $named);
            } elseif ($named) {
                self::credential($value, $secrets);
            }
        }
    }

    /** @param list<string> $secrets */
    private static function credential(mixed $value, array &$secrets): void
    {
        if (!\is_scalar($value)) {
            return;
        }
        $value = trim((string) $value);
        $secrets[] = $value;
        // "Bearer <token>", "Basic <base64>", "user:password": each part a secret too.
        if (1 === preg_match('~^[A-Za-z][A-Za-z0-9-]*\s+(\S+)$~', $value, $match)) {
            $secrets[] = $match[1];
        }
        if (str_contains($value, ':') && !str_contains($value, ' ')) {
            $secrets[] = explode(':', $value, 2)[1];
        }
    }

    /**
     * The secrets worth masking, the longest first: a value of less than four
     * characters (a flag, an id) would mask ordinary words.
     *
     * @param list<string> $secrets
     *
     * @return list<string>
     */
    private static function unique(array $secrets): array
    {
        $secrets = array_values(array_unique(array_filter($secrets, static fn (string $secret): bool => \strlen($secret) >= 4)));
        usort($secrets, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $secrets;
    }
}
