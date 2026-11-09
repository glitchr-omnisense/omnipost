<?php

namespace Omnishare\Tests;

use Omnishare\Network;
use Omnishare\NetworkInterface;
use Omnishare\Registry;
use Omnishare\Shareable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each network's address for one page: what it is given, encoded as RFC 3986
 * says (a space is %20, "(", "'", "&" and "!" escaped), the parameters
 * nobody gave left out.
 */
final class NetworksTest extends TestCase
{
    private const URL = 'https%3A%2F%2Flesringostars.example%2Fjapon%2Farticles%2Fle-fuji';
    private const TITLE = 'Le%20Fuji%20%28l%27ascension%29%20%26%20nous%20%21';
    private const IMAGE = 'https%3A%2F%2Flesringostars.example%2Fimages%2Fab%2Fcd%2Fimage.jpg';
    private const TAGS = '%20%23japon%20%23fuji';

    private static function page(bool $everything = false): Shareable
    {
        return $everything
            ? new Shareable('https://lesringostars.example/japon/articles/le-fuji', "Le Fuji (l'ascension) & nous !", null, 'https://lesringostars.example/images/ab/cd/image.jpg', ['#japon', ' fuji ', ''], 'lesringostars')
            : new Shareable('https://lesringostars.example/japon/articles/le-fuji', "Le Fuji (l'ascension) & nous !", image: 'https://lesringostars.example/images/ab/cd/image.jpg');
    }

    /** @return iterable<string, array{NetworkInterface, string, string}> */
    public static function networks(): iterable
    {
        $u = self::URL;
        $t = self::TITLE;
        yield 'facebook' => [new Network\Facebook(), "https://www.facebook.com/sharer/sharer.php?u=$u", "https://www.facebook.com/sharer/sharer.php?u=$u"];
        yield 'x' => [new Network\X(), "https://twitter.com/intent/tweet?url=$u&text=$t", "https://twitter.com/intent/tweet?url=$u&text=$t&hashtags=japon%2Cfuji&via=lesringostars"];
        yield 'pinterest' => [new Network\Pinterest(), "https://pinterest.com/pin/create/button/?url=$u&media=".self::IMAGE."&description=$t", "https://pinterest.com/pin/create/button/?url=$u&media=".self::IMAGE."&description=$t"];
        yield 'linkedin' => [new Network\LinkedIn(), "https://www.linkedin.com/sharing/share-offsite/?url=$u", "https://www.linkedin.com/sharing/share-offsite/?url=$u"];
        yield 'bluesky' => [new Network\Bluesky(), "https://bsky.app/intent/compose?text=$t%20$u", "https://bsky.app/intent/compose?text=$t".self::TAGS."%20$u"];
        yield 'mastodon' => [new Network\Mastodon(), "https://mastodon.social/share?text=$t%20$u", "https://mastodon.social/share?text=$t".self::TAGS."%20$u"];
        yield 'mastodon on an instance' => [new Network\Mastodon('https://Piaille.fr/about'), "https://piaille.fr/share?text=$t%20$u", "https://piaille.fr/share?text=$t".self::TAGS."%20$u"];
        yield 'threads' => [new Network\Threads(), "https://www.threads.com/intent/post?text=$t&url=$u", "https://www.threads.com/intent/post?text=$t".self::TAGS."&url=$u"];
        yield 'whatsapp' => [new Network\WhatsApp(), "https://wa.me/?text=$t%20$u", "https://wa.me/?text=$t%20$u"];
        yield 'telegram' => [new Network\Telegram(), "https://t.me/share/url?url=$u&text=$t", "https://t.me/share/url?url=$u&text=$t"];
        yield 'reddit' => [new Network\Reddit(), "https://www.reddit.com/submit?url=$u&title=$t", "https://www.reddit.com/submit?url=$u&title=$t"];
        yield 'email' => [new Network\Email(), "mailto:?subject=$t&body=$u", "mailto:?subject=$t&body=$u"];
        yield 'copy' => [new Network\CopyLink(), 'https://lesringostars.example/japon/articles/le-fuji', 'https://lesringostars.example/japon/articles/le-fuji'];
        yield 'native' => [new Network\Native(), 'https://lesringostars.example/japon/articles/le-fuji', 'https://lesringostars.example/japon/articles/le-fuji'];
    }

    /** @dataProvider networks (PHPUnit 9, as an application's simple-phpunit runs it) */
    #[DataProvider('networks')]
    public function testEachNetworkIsGivenWhatItTakes(NetworkInterface $network, string $plain, string $everything): void
    {
        self::assertSame($plain, $network->shareUrl(self::page()));
        self::assertSame($everything, $network->shareUrl(self::page(true)), 'with hashtags and an account to credit');
        self::assertSame('omnishare.'.$network->name(), $network->labelKey());
        self::assertNotSame([], $network->uses());
        self::assertContains('url', $network->uses());
    }

    public function testATextIsPostedInsteadOfTheTitleAndTheMailSaysItAboveTheAddress(): void
    {
        $page = new Shareable('https://example.com/a', 'Le Fuji', 'Une ascension de nuit');

        self::assertSame('https://twitter.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Fa&text=Une%20ascension%20de%20nuit', (new Network\X())->shareUrl($page));
        self::assertSame('https://www.reddit.com/submit?url=https%3A%2F%2Fexample.com%2Fa&title=Le%20Fuji', (new Network\Reddit())->shareUrl($page), 'a Reddit post keeps the title');
        self::assertSame('mailto:?subject=Le%20Fuji&body=Une%20ascension%20de%20nuit%0A%0Ahttps%3A%2F%2Fexample.com%2Fa', (new Network\Email())->shareUrl($page));
    }

    public function testWithoutAPicturePinterestIsAskedForNone(): void
    {
        self::assertSame('https://pinterest.com/pin/create/button/?url=https%3A%2F%2Fexample.com%2Fa&description=Le%20Fuji', (new Network\Pinterest())->shareUrl(new Shareable('https://example.com/a', 'Le Fuji')));
    }

    public function testMastodonAsksTheVisitorOnlyWhenNoInstanceIsConfigured(): void
    {
        self::assertSame('instance', (new Network\Mastodon())->action());
        self::assertNull((new Network\Mastodon())->instance());
        self::assertNull((new Network\Mastodon('piaille.fr'))->action());
        self::assertSame('piaille.fr', (new Network\Mastodon(' piaille.fr '))->instance());
        self::assertSame('https://mastodon.online/share?text=a%20https%3A%2F%2Fexample.com%2Fa', Registry::defaults('mastodon.online')->get('mastodon')->shareUrl(new Shareable('https://example.com/a', 'a')));
    }

    public function testTheNetworksSayHowTheyAreDrawn(): void
    {
        $registry = Registry::defaults();
        $icons = $colors = $actions = [];
        foreach ($registry->names() as $name) {
            $network = $registry->get($name);
            $icons[$name] = $network->icon();
            $colors[$name] = $network->color();
            $actions[$name] = $network->action();
        }

        self::assertSame(['facebook', 'x', 'pinterest', 'linkedin', 'bluesky', 'mastodon', 'threads', 'whatsapp', 'telegram', 'reddit', 'envelope', 'link', 'share'], array_values($icons));
        self::assertSame(['email' => null, 'copy' => null, 'native' => null], array_filter($colors, static fn (?string $c) => null === $c));
        foreach (array_filter($colors) as $color) {
            self::assertMatchesRegularExpression('~^#[0-9a-f]{6}$~', $color);
        }
        self::assertSame(['mastodon' => 'instance', 'copy' => 'copy', 'native' => 'share'], array_filter($actions));
    }
}
