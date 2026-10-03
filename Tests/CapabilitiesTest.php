<?php

namespace Omnipost\Tests;

use Omnipost\Model\Capabilities;
use Omnipost\Model\PostKind;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase
{
    public function testARatioIsAcceptedWithinTheToleranceOfOneOfThose(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], aspectRatios: ['9:16', '4:5', '1:1'], ratioTolerance: 0.01);

        self::assertTrue($capabilities->acceptsRatio(9 / 16));
        self::assertTrue($capabilities->acceptsRatio(0.5625 * 1.009), 'under one percent over');
        self::assertTrue($capabilities->acceptsRatio(0.5625 * 0.991), 'under one percent under');
        self::assertFalse($capabilities->acceptsRatio(0.5625 * 1.02));
        self::assertFalse($capabilities->acceptsRatio(0.5625 * 0.98));
        self::assertTrue($capabilities->acceptsRatio(0.8));
        self::assertTrue($capabilities->acceptsRatio(1.0));
        self::assertFalse($capabilities->acceptsRatio(16 / 9));
    }

    public function testTheToleranceIsAFractionOfTheRatio(): void
    {
        $wide = new Capabilities([PostKind::VIDEO], aspectRatios: ['16:9'], ratioTolerance: 0.05);

        self::assertTrue($wide->acceptsRatio(1.85), 'four percent over 16:9');
        self::assertFalse($wide->acceptsRatio(1.9));
    }

    public function testNoRatioListedIsAnyRatio(): void
    {
        self::assertTrue((new Capabilities([PostKind::VIDEO]))->acceptsRatio(2.39));
    }

    public function testTheKindsSupported(): void
    {
        $capabilities = new Capabilities([PostKind::REEL, PostKind::IMAGE]);

        self::assertTrue($capabilities->supports(PostKind::REEL));
        self::assertFalse($capabilities->supports(PostKind::STORY));
    }
}
