<?php

namespace Omnipost\Tests;

use Omnipost\Exception\InvalidConfigException;
use Omnipost\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    private static function registry(): Registry
    {
        return new Registry([new StubFactory(), new StubWallFactory()], [
            'fediverse' => ['factory' => 'stub', 'options' => ['token' => 'a']],
            'wall' => ['factory' => 'wall'],
        ]);
    }

    public function testEachProviderIsBuiltOnceByName(): void
    {
        $registry = self::registry();

        self::assertTrue($registry->has('fediverse'));
        self::assertFalse($registry->has('tiktok'));
        self::assertSame($registry->get('fediverse'), $registry->get('fediverse'));
        self::assertSame('a', $registry->get('fediverse')->token);
        self::assertSame(['fediverse', 'wall'], $registry->names());
        self::assertSame(['fediverse', 'wall'], array_keys($registry->all()));
        self::assertSame(['stub', 'wall'], $registry->factories());
    }

    public function testThePublishersAndTheFeedsAmongThem(): void
    {
        $registry = self::registry();

        self::assertSame([$registry->get('fediverse')], $registry->publishers());
        self::assertSame([$registry->get('wall')], $registry->feeds());
    }

    public function testAProviderBuiltAfreshWithATokenFromElsewhere(): void
    {
        $registry = self::registry();

        // Kept in a database, over the configured options: a fresh provider, not kept.
        self::assertSame(['token' => 'a'], $registry->options('fediverse'));
        $stored = $registry->create('fediverse', ['token' => 'b']);
        self::assertNotSame($registry->get('fediverse'), $stored);
        self::assertSame('b', $stored->token);
        self::assertSame('a', $registry->get('fediverse')->token);
    }

    public function testAnUnknownProviderSaysWhichAreConfigured(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "tiktok" provider; configured: fediverse, wall.');
        self::registry()->get('tiktok');
    }

    public function testAnUnknownFactorySaysWhichAreInstalled(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "tiktok" factory for the "tiktok" provider; installed: stub.');
        (new Registry([new StubFactory()], ['tiktok' => ['factory' => 'tiktok']]))->get('tiktok');
    }

    public function testRequiredOptionsAreCheckedWhenTheProviderIsAskedFor(): void
    {
        // An environment variable not set: declared, and not configured.
        $registry = new Registry([new StubFactory()], ['fediverse' => ['factory' => 'stub', 'options' => ['token' => '', 'handle' => null]]]);
        self::assertTrue($registry->has('fediverse'), 'nothing is built, nor checked, before get()');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "stub" provider needs: token.');
        $registry->get('fediverse');
    }

    public function testAnOptionLeftEmptyIsItsDefault(): void
    {
        $provider = (new StubFactory())->create(['token' => 't', 'handle' => null]);

        self::assertSame('stub', $provider->handle);
        self::assertSame('stub', (new StubFactory())->getName());
    }
}
