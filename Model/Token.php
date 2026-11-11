<?php

namespace Omnipost\Model;

/** An access token and when it dies: a site stores it and asks the provider to refresh it in time. */
final class Token
{
    /** @param list<string> $scopes */
    public function __construct(
        public readonly string $accessToken,
        public readonly ?\DateTimeImmutable $expiresAt = null,
        public readonly ?string $refreshToken = null,
        public readonly array $scopes = [],
        public readonly ?string $accountId = null,
    ) {
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    /** True within $days of the end: time to refresh. */
    public function isExpiring(int $days = 10, ?\DateTimeImmutable $now = null): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= ($now ?? new \DateTimeImmutable())->modify("+$days days");
    }

    /** @return array{access_token: string, expires_at?: string, refresh_token?: string, scopes?: non-empty-list<string>, account_id?: string} */
    public function toArray(): array
    {
        return array_filter([
            'access_token' => $this->accessToken,
            'expires_at' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
            'refresh_token' => $this->refreshToken,
            'scopes' => $this->scopes ?: null,
            'account_id' => $this->accountId,
        ], static fn ($v) => null !== $v);
    }

    /** @param array<mixed> $data what toArray() gave, as a site stored it */
    public static function fromArray(array $data): self
    {
        $expiresAt = $data['expires_at'] ?? null;

        return new self(
            self::text($data['access_token'] ?? null),
            null !== $expiresAt ? new \DateTimeImmutable(self::text($expiresAt, 'expires_at')) : null,
            self::textOrNull($data['refresh_token'] ?? null, 'refresh_token'),
            array_values(array_map(static fn (mixed $scope): string => self::text($scope), (array) ($data['scopes'] ?? []))),
            self::textOrNull($data['account_id'] ?? null, 'account_id'),
        );
    }

    /**
     * A stored value as PHP's (string) gives it ("" for what is not a scalar); with $key, what is
     * not a scalar is the TypeError PHP throws on a string parameter.
     */
    private static function text(mixed $value, ?string $key = null): string
    {
        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }
        if (null !== $key) {
            throw new \TypeError(\sprintf('The token\'s "%s" must be a string, %s given.', $key, get_debug_type($value)));
        }

        return '';
    }

    private static function textOrNull(mixed $value, string $key): ?string
    {
        return null === $value ? null : self::text($value, $key);
    }
}
