<?php

namespace Omnipost\Tests;

use Omnipost\Model\Media;
use Omnipost\Model\MediaKind;
use Omnipost\Model\Post;
use Omnipost\Model\PostKind;
use Omnipost\Model\Variant;
use Omnipost\Platform;
use PHPUnit\Framework\TestCase;

final class PostTest extends TestCase
{
    private static function reel(): Post
    {
        return Post::reel(Media::video('https://site.example/reel.mp4', '/var/reels/reel.mp4', 42.0, 1080, 1920, 'https://site.example/cover.jpg'), 'Strauss, tonight.', null, ['violin', '#sonata']);
    }

    public function testAReelIsOneVideoItsCoverTheVideos(): void
    {
        $post = self::reel();

        self::assertSame(PostKind::REEL, $post->kind);
        self::assertCount(1, $post->media);
        self::assertSame('https://site.example/cover.jpg', $post->coverUrl);
        self::assertSame('9:16', $post->media[0]->aspectRatio());
        self::assertEqualsWithDelta(0.5625, $post->media[0]->ratio(), 0.0001);
        self::assertNull(Media::video('https://site.example/reel.mp4')->ratio(), 'a size unknown is no ratio');
    }

    public function testForANetworkTheVariantsFieldsReplaceThePosts(): void
    {
        $short = Media::video('https://site.example/short.mp4', null, 30.0, 1080, 1920);
        $post = self::reel()
            ->withVariant(new Variant(Platform::YOUTUBE, title: 'Strauss, Sonata', tags: ['shorts'], media: [$short], coverUrl: 'https://site.example/yt.jpg', options: ['privacy' => 'unlisted']))
            ->withVariant(new Variant(Platform::INSTAGRAM, caption: 'Ce soir, Strauss.'));

        $youtube = $post->for(Platform::YOUTUBE);
        self::assertSame('Strauss, Sonata', $youtube->title);
        self::assertSame('Strauss, tonight.', $youtube->caption, 'what the variant leaves null is the post\'s');
        self::assertSame(['shorts'], $youtube->tags);
        self::assertSame([$short], $youtube->media);
        self::assertSame('https://site.example/yt.jpg', $youtube->coverUrl);
        self::assertSame(['privacy' => 'unlisted'], $youtube->options);
        self::assertSame([], $youtube->variants, 'no variants left');

        $instagram = $post->for(Platform::INSTAGRAM);
        self::assertSame('Ce soir, Strauss.', $instagram->caption);
        self::assertNull($instagram->title);
        self::assertSame(['violin', '#sonata'], $instagram->tags);

        $tiktok = $post->for(Platform::TIKTOK);
        self::assertSame('Strauss, tonight.', $tiktok->caption, 'no variant: the post as it is');
        self::assertSame([], $tiktok->variants);
        self::assertNotNull($post->variant(Platform::YOUTUBE));
        self::assertNull($post->variant(Platform::TIKTOK));
    }

    public function testTheTextIsTheCaptionWithItsHashtags(): void
    {
        self::assertSame("Strauss, tonight.\n\n#violin #sonata", self::reel()->text(), 'one # each, given or not');
        self::assertSame('Strauss, tonight.', Post::reel(Media::video('https://site.example/reel.mp4'), 'Strauss, tonight.')->text());
        self::assertSame('#violin', Post::reel(Media::video('https://site.example/reel.mp4'), '', null, ['violin'])->text());
    }

    public function testTheVideoIsTheFirstOneAmongTheMedia(): void
    {
        $video = Media::video('https://site.example/b.mp4');
        $carousel = Post::carousel([Media::image('https://site.example/a.jpg'), $video]);

        self::assertSame($video, $carousel->video());
        self::assertSame(MediaKind::VIDEO, $carousel->video()->kind);
        self::assertNull(Post::image(Media::image('https://site.example/a.jpg'))->video());
    }

    public function testAScheduledPostKeepsEverythingElse(): void
    {
        $at = new \DateTimeImmutable('2030-01-01 18:00');
        $post = self::reel()->withVariant(new Variant(Platform::YOUTUBE, title: 'T'))->withScheduledAt($at);

        self::assertSame($at, $post->scheduledAt);
        self::assertSame('T', $post->for(Platform::YOUTUBE)->title);
        self::assertSame($at, $post->for(Platform::YOUTUBE)->scheduledAt);
    }
}
