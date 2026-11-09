<?php

namespace Omnishare;

use Omnishare\Exception\InvalidShareableException;

/**
 * What is shared: a page, by its canonical address, with what the networks
 * show of it. Each network takes the part it knows (NetworkInterface::uses()):
 * Facebook and LinkedIn the address alone (they read the page's og:* tags
 * themselves), X the address, the message, the hashtags and the account to
 * credit, Pinterest the picture as well, and so on.
 *
 * Addresses are whole (scheme and host): a share page opens on another site,
 * where a path means nothing.
 */
final readonly class Shareable
{
    /** @var list<string> */
    public array $hashtags;

    /**
     * @param string        $url      the page's canonical address, whole
     * @param string        $title    the page's title: the e-mail's subject, Reddit's title, the message when there is no text
     * @param string|null   $text     a sentence to post with the link; the title is posted when null
     * @param string|null   $image    the page's share picture, whole (Pinterest's pin)
     * @param list<string>  $hashtags without their "#"
     * @param string|null   $via      an account to credit, without its "@" (X)
     */
    public function __construct(
        public string $url,
        public string $title,
        public ?string $text = null,
        public ?string $image = null,
        array $hashtags = [],
        public ?string $via = null,
    ) {
        self::whole($url, 'url');
        if (null !== $image && '' !== $image) {
            self::whole($image, 'image');
        }
        $tags = [];
        foreach ($hashtags as $tag) {
            $tag = ltrim(trim($tag), '#');
            if ('' !== $tag) {
                $tags[] = $tag;
            }
        }
        $this->hashtags = $tags;
    }

    /** What a network posts: the text when there is one, the title otherwise. */
    public function message(): string
    {
        return null !== $this->text && '' !== $this->text ? $this->text : $this->title;
    }

    /** @return array{url: string, title: string, text: ?string, image: ?string, hashtags: list<string>, via: ?string} */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'title' => $this->title,
            'text' => $this->text,
            'image' => $this->image,
            'hashtags' => $this->hashtags,
            'via' => $this->via,
        ];
    }

    private static function whole(string $address, string $what): void
    {
        $scheme = parse_url($address, \PHP_URL_SCHEME);
        $host = parse_url($address, \PHP_URL_HOST);
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), ['http', 'https'], true) || !\is_string($host) || '' === $host) {
            throw new InvalidShareableException(\sprintf('The %s to share must be a whole http(s) address, "%s" given.', $what, $address));
        }
    }
}
