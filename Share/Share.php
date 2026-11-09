<?php

namespace Omnishare;

/**
 * A Shareable and its links, in the order they were asked for: what a
 * template draws. toArray() is the one JSON Twig, Blade and React read alike:
 *
 *     {"url": ..., "title": ..., "text": ..., "image": ..., "hashtags": [...], "via": ...,
 *      "links": {"facebook": {"network": "facebook", "url": ..., "label": "omnishare.facebook",
 *                             "icon": "facebook", "color": "#1877f2", "newTab": true, "action": null}, ...}}
 */
final readonly class Share implements \JsonSerializable
{
    /** @param array<string, Link> $links by network name */
    public function __construct(
        public Shareable $shareable,
        public array $links,
    ) {
    }

    public function link(string $network): ?Link
    {
        return $this->links[$network] ?? null;
    }

    /** The address of one network's link, null when it was not asked for. */
    public function url(string $network): ?string
    {
        return $this->link($network)?->url;
    }

    /**
     * @return array{url: string, title: string, text: ?string, image: ?string, hashtags: list<string>, via: ?string,
     *               links: array<string, array{network: string, url: string, label: string, icon: string, color: ?string, newTab: bool, action: ?string}>}
     */
    public function toArray(): array
    {
        return $this->shareable->toArray() + ['links' => array_map(static fn (Link $link) => $link->toArray(), $this->links)];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
