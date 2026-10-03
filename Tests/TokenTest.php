<?php

namespace Omnipost\Tests;

use Omnipost\Model\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testATokenExpiresAtItsEnd(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 12:00:00 UTC');

        self::assertFalse((new Token('t', $now->modify('+1 second')))->isExpired($now));
        self::assertTrue((new Token('t', $now))->isExpired($now));
        self::assertTrue((new Token('t', $now->modify('-1 day')))->isExpired($now));
        self::assertFalse((new Token('t'))->isExpired($now), 'no end known: not expired');
        self::assertTrue((new Token('t', new \DateTimeImmutable('-1 minute')))->isExpired(), 'against now by default');
    }

    public function testATokenIsExpiringWithinSomeDaysOfItsEnd(): void
    {
        $now = new \DateTimeImmutable('2026-03-01 12:00:00 UTC');
        $token = new Token('t', $now->modify('+12 days'));

        self::assertFalse($token->isExpiring(10, $now));
        self::assertTrue($token->isExpiring(12, $now));
        self::assertTrue($token->isExpiring(30, $now));
        self::assertTrue((new Token('t', $now->modify('-1 day')))->isExpiring(10, $now), 'expired is expiring');
        self::assertFalse((new Token('t'))->isExpiring(10, $now));
        self::assertFalse((new Token('t', new \DateTimeImmutable('+60 days')))->isExpiring());
    }

    public function testATokenGoesToAnArrayAndBack(): void
    {
        $token = new Token('IGQ...', new \DateTimeImmutable('2026-05-01T10:00:00+00:00'), '1//refresh', ['instagram_business_basic'], '178414');
        $array = $token->toArray();

        self::assertSame(['access_token' => 'IGQ...', 'expires_at' => '2026-05-01T10:00:00+00:00', 'refresh_token' => '1//refresh', 'scopes' => ['instagram_business_basic'], 'account_id' => '178414'], $array);
        self::assertEquals($token, Token::fromArray($array));
        self::assertEquals($token, Token::fromArray(json_decode((string) json_encode($array), true)), 'through JSON, as a site stores it');
    }

    public function testWhatIsNotKnownIsLeftOutOfTheArray(): void
    {
        self::assertSame(['access_token' => 't'], (new Token('t'))->toArray());
        self::assertEquals(new Token('t'), Token::fromArray(['access_token' => 't']));
    }
}
