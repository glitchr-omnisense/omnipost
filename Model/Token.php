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

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['access_token'],
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
            $data['refresh_token'] ?? null,
            (array) ($data['scopes'] ?? []),
            $data['account_id'] ?? null,
        );
    }
}
