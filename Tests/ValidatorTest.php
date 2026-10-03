<?php

namespace Omnipost\Tests;

use Omnipost\Model\Capabilities;
use Omnipost\Model\Media;
use Omnipost\Model\MediaKind;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Violation;
use Omnipost\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /** @return array<string, string> path => message */
    private static function violations(Post $post, Capabilities $capabilities): array
    {
        $violations = [];
        foreach ((new Validator())->validate($post, $capabilities) as $violation) {
            self::assertInstanceOf(Violation::class, $violation);
            $violations[$violation->path] = $violation->message;
        }

        return $violations;
    }

    private static function video(?float $duration = 42.0, ?int $width = 1080, ?int $height = 1920, ?int $size = null, ?string $mime = null, string $url = 'https://site.example/reel.mp4'): Media
    {
        return new Media(MediaKind::VIDEO, $url, null, $mime, $width, $height, $duration, $size);
    }

    public function testAPostWithinTheCapabilitiesHasNoViolation(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], 3.0, 900.0, ['9:16'], 0.01, 1 << 30, null, 2200, 30, true, 100, 1, ['video/mp4', 'mov'], [], 320, 320);

        self::assertSame([], self::violations(Post::reel(self::video(size: 50 << 20, mime: 'video/mp4'), 'A caption', 'A title', ['violin']), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(null, null, null), 'A caption', 'A title'), $capabilities), 'what is not known is not judged');
    }

    public function testTheKindMustBeAccepted(): void
    {
        $violations = self::violations(Post::image(Media::image('https://site.example/a.jpg')), new Capabilities([PostKind::REEL, PostKind::VIDEO]));

        self::assertSame(['kind' => 'image posts are not accepted (accepted: reel, video).'], $violations);
    }

    public function testTheNumberOfMedia(): void
    {
        $images = [Media::image('https://site.example/a.jpg'), Media::image('https://site.example/b.jpg'), Media::image('https://site.example/c.jpg')];

        self::assertSame(['media' => '3 media, at most 2.'], self::violations(Post::carousel($images), new Capabilities([PostKind::CAROUSEL], mediaMaxCount: 2)));
        self::assertSame([], self::violations(Post::carousel($images), new Capabilities([PostKind::CAROUSEL], mediaMaxCount: 3)));
        self::assertSame(['media' => 'No media.'], self::violations(Post::carousel([]), new Capabilities([PostKind::CAROUSEL])));
    }

    public function testTheVideosDuration(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], minDuration: 3.0, maxDuration: 90.0);

        self::assertSame(['media[0].duration' => '2.0 s, at least 3 s.'], self::violations(Post::reel(self::video(2.0)), $capabilities));
        self::assertSame(['media[0].duration' => '90.5 s, at most 90 s.'], self::violations(Post::reel(self::video(90.5)), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(3.0)), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(90.0)), $capabilities));
    }

    public function testTheRatioWithinItsTolerance(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], aspectRatios: ['9:16', '1:1'], ratioTolerance: 0.01);

        self::assertSame([], self::violations(Post::reel(self::video(width: 1080, height: 1920)), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(width: 1080, height: 1910)), $capabilities), 'half a percent off');
        self::assertSame([], self::violations(Post::reel(self::video(width: 1080, height: 1080)), $capabilities));
        self::assertSame(['media[0].ratio' => '16:9 is not accepted (accepted: 9:16, 1:1).'], self::violations(Post::reel(self::video(width: 1920, height: 1080)), $capabilities));
        self::assertArrayHasKey('media[0].ratio', self::violations(Post::reel(self::video(width: 1080, height: 1800)), $capabilities), 'six percent off');
    }

    public function testTheSizes(): void
    {
        $capabilities = new Capabilities([PostKind::REEL, PostKind::IMAGE], maxVideoSize: 1 << 30, maxImageSize: 8 << 20, minWidth: 320, minHeight: 400);

        self::assertSame(['media[0].size' => '1.5 GB, at most 1 GB.'], self::violations(Post::reel(self::video(size: 3 << 29)), $capabilities));
        self::assertSame(['media[0].size' => '9 MB, at most 8 MB.'], self::violations(Post::image(new Media(MediaKind::IMAGE, 'https://site.example/a.jpg', size: 9 << 20)), $capabilities));
        self::assertSame([], self::violations(Post::image(new Media(MediaKind::IMAGE, 'https://site.example/a.jpg', size: 8 << 20)), $capabilities));
        self::assertSame(
            ['media[0].width' => '200 px wide, at least 320.', 'media[0].height' => '300 px high, at least 400.'],
            self::violations(Post::image(Media::image('https://site.example/a.jpg', null, 200, 300)), $capabilities),
        );
    }

    public function testTheFormatsByMimeTypeOrExtension(): void
    {
        $capabilities = new Capabilities([PostKind::REEL, PostKind::IMAGE], videoFormats: ['video/mp4', 'mov'], imageFormats: ['image/jpeg']);

        self::assertSame([], self::violations(Post::reel(self::video(mime: 'video/mp4')), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(mime: 'video/quicktime', url: 'https://site.example/reel.MOV?signature=x')), $capabilities), 'by its extension');
        self::assertSame(['media[0].format' => 'video/webm is not accepted (accepted: video/mp4, mov).'], self::violations(Post::reel(self::video(mime: 'video/webm', url: 'https://site.example/reel.webm')), $capabilities));
        self::assertSame(['media[0].format' => 'image/png is not accepted (accepted: image/jpeg).'], self::violations(Post::image(new Media(MediaKind::IMAGE, 'https://site.example/a.png', mime: 'image/png')), $capabilities));
    }

    public function testTheCaptionIsMeasuredWithItsHashtags(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], captionMaxLength: 20);

        self::assertSame([], self::violations(Post::reel(self::video(), str_repeat('é', 20)), $capabilities), 'characters, not bytes');
        self::assertSame(['caption' => '21 characters with the hashtags, at most 20.'], self::violations(Post::reel(self::video(), str_repeat('é', 21)), $capabilities));
        self::assertSame(['caption' => '21 characters with the hashtags, at most 20.'], self::violations(Post::reel(self::video(), 'Twelve chars', null, ['violin']), $capabilities));
    }

    public function testTheNumberOfHashtags(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], maxTags: 2);

        self::assertSame(['tags' => '3 hashtags, at most 2.'], self::violations(Post::reel(self::video(), '', null, ['a', 'b', 'c']), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(), '', null, ['a', 'b']), $capabilities));
    }

    public function testTheTitleRequiredAndItsLength(): void
    {
        $capabilities = new Capabilities([PostKind::REEL], titleRequired: true, titleMaxLength: 10);

        self::assertSame(['title' => 'A title is required.'], self::violations(Post::reel(self::video()), $capabilities));
        self::assertSame(['title' => 'A title is required.'], self::violations(Post::reel(self::video(), '', '  '), $capabilities));
        self::assertSame(['title' => '11 characters, at most 10.'], self::violations(Post::reel(self::video(), '', 'Eleven char'), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video(), '', 'Ten chars!'), $capabilities));
        self::assertSame([], self::violations(Post::reel(self::video()), new Capabilities([PostKind::REEL])), 'no title where none is asked');
    }

    public function testSchedulingOnlyWhereTheNetworkOffersIt(): void
    {
        $post = Post::reel(self::video())->withScheduledAt(new \DateTimeImmutable('+1 day'));

        self::assertSame(['scheduledAt' => 'Scheduling is not offered: the site must post it at that time itself.'], self::violations($post, new Capabilities([PostKind::REEL])));
        self::assertSame([], self::violations($post, new Capabilities([PostKind::REEL], scheduling: true)));
    }

    public function testEveryViolationIsListedAndReadable(): void
    {
        $violations = (new Validator())->validate(
            Post::reel(self::video(2.0, 1920, 1080), str_repeat('x', 30)),
            new Capabilities([PostKind::IMAGE], minDuration: 3.0, aspectRatios: ['9:16'], captionMaxLength: 20, titleRequired: true),
        );

        self::assertSame(['kind', 'media[0].duration', 'media[0].ratio', 'caption', 'title'], array_map(static fn (Violation $v) => $v->path, $violations));
        self::assertSame('media[0].duration: 2.0 s, at least 3 s.', (string) $violations[1]);
    }
}
