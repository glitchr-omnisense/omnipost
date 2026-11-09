<?php

namespace Omnishare\Tests;

use Omnishare\Exception\InvalidShareableException;
use Omnishare\Exception\OmnishareException;
use Omnishare\Exception\UnknownNetworkException;
use Omnishare\Labels;
use Omnishare\Link;
use Omnishare\Network\CopyLink;
use Omnishare\Network\Facebook;
use Omnishare\Network\Mastodon;
use Omnishare\Network\X;
use Omnishare\NetworkInterface;
use Omnishare\Registry;
use Omnishare\Shareable;
use PHPUnit\Framework\TestCase;

/** A Registry's links of a page, the JSON templates read, a Shareable's checks, the labels. */
final class ShareTest extends TestCase
{
    public function testTheLinksComeInTheOrderAskedAndTheJsonHoldsWhatATemplateDraws(): void
    {
        $share = Registry::defaults()->share(new Shareable('https://example.com/a', 'Le Fuji', image: 'https://example.com/i.jpg'), ['x', 'facebook', 'email', 'native']);

        self::assertSame(['x', 'facebook', 'email', 'native'], array_keys($share->links));
        self::assertSame('https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fexample.com%2Fa', $share->url('facebook'));
        self::assertNull($share->url('pinterest'));

        self::assertSame($share->toArray(), json_decode((string) json_encode($share), true), 'the JSON is toArray()');
        $json = $share->toArray();
        self::assertSame(['url' => 'https://example.com/a', 'title' => 'Le Fuji', 'text' => null, 'image' => 'https://example.com/i.jpg', 'hashtags' => [], 'via' => null], \array_slice($json, 0, 6));
        self::assertSame([
            'network' => 'facebook',
            'url' => 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fexample.com%2Fa',
            'label' => 'omnishare.facebook',
            'icon' => 'facebook',
            'color' => '#1877f2',
            'newTab' => true,
            'action' => null,
        ], $json['links']['facebook']);
        self::assertFalse($json['links']['email']['newTab'], 'a mailto: opens the mail client, not a tab');
        self::assertSame(['newTab' => false, 'action' => 'share'], array_intersect_key($json['links']['native'], ['newTab' => 0, 'action' => 0]));
    }

    /** Mastodon without an instance: a web share page, in a new tab as the script's markup has it, whether the script is loaded or not. */
    public function testMastodonsLinkWithoutAnInstanceOpensInANewTab(): void
    {
        $link = Link::of(new Mastodon(), new Shareable('https://example.com/a', 'Le Fuji'));

        self::assertSame('instance', $link->action);
        self::assertTrue($link->newTab());
        self::assertFalse(Link::of(new CopyLink(), new Shareable('https://example.com/a', 'Le Fuji'))->newTab(), 'copy: the page\'s own address, no tab');
    }

    public function testEveryNetworkByDefault(): void
    {
        $registry = Registry::defaults();

        self::assertSame(['facebook', 'x', 'pinterest', 'linkedin', 'bluesky', 'mastodon', 'threads', 'whatsapp', 'telegram', 'reddit', 'email', 'copy', 'native'], $registry->names());
        self::assertSame($registry->names(), array_keys($registry->share(new Shareable('https://example.com/a', 'a'))->links));
    }

    public function testAnApplicationAddsOrReplacesANetwork(): void
    {
        $registry = new Registry([new Facebook()]);
        $registry->add(new X());
        $registry->add(new class implements NetworkInterface {
            public function name(): string { return 'facebook'; }
            public function labelKey(): string { return 'app.facebook'; }
            public function icon(): string { return 'fb'; }
            public function color(): ?string { return null; }
            public function uses(): array { return ['url']; }
            public function shareUrl(Shareable $shareable): string { return 'https://m.facebook.com/sharer.php?u='.rawurlencode($shareable->url); }
            public function action(): ?string { return null; }
        });

        self::assertSame(['facebook', 'x'], $registry->names());
        self::assertSame('https://m.facebook.com/sharer.php?u=https%3A%2F%2Fexample.com%2Fa', $registry->share(new Shareable('https://example.com/a', 'a'), ['facebook'])->url('facebook'));
        self::assertTrue($registry->has('x'));
        self::assertFalse($registry->has('myspace'));
    }

    public function testAnUnknownNetworkIsSaid(): void
    {
        $this->expectException(UnknownNetworkException::class);
        $this->expectExceptionMessage('No share network "myspace"; known: facebook, x, pinterest');

        Registry::defaults()->share(new Shareable('https://example.com/a', 'a'), ['facebook', 'myspace']);
    }

    public function testAnAddressMustBeWhole(): void
    {
        foreach (['/japon/articles/le-fuji', 'example.com/a', 'javascript:alert(1)', 'ftp://example.com/a'] as $address) {
            try {
                new Shareable($address, 'a');
                self::fail($address.' was taken');
            } catch (InvalidShareableException $e) {
                self::assertInstanceOf(OmnishareException::class, $e);
            }
        }
        $this->expectException(InvalidShareableException::class);
        new Shareable('https://example.com/a', 'a', image: '/images/a.jpg');
    }

    public function testTheLabels(): void
    {
        self::assertSame('Épingler sur Pinterest', Labels::get('omnishare.pinterest', 'fr_FR'));
        self::assertSame('Pin on Pinterest', Labels::get('omnishare.pinterest', 'de'));
        self::assertSame('omnishare.myspace', Labels::get('omnishare.myspace'));
        $registry = Registry::defaults();
        foreach (Labels::locales() as $locale) {
            foreach ($registry->names() as $name) {
                self::assertArrayHasKey($registry->get($name)->labelKey(), Labels::catalogue($locale), $locale);
            }
            self::assertSame(array_keys(Labels::catalogue('en')), array_keys(Labels::catalogue($locale)));
        }
    }
}
