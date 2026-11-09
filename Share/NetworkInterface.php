<?php

namespace Omnipost\Share;

/**
 * Omnipost's share contract: a place a visitor can share a page to - a
 * network's own share page, an e-mail, the clipboard, the device's share
 * sheet. A network builds an address from a Shareable and says how it is
 * shown; it calls nothing and holds no credential (publishing from an
 * account is PublisherInterface's part). The core holds every network that
 * has a share page (Registry::defaults()); an application, or a provider
 * package, adds its own with Registry::add().
 */
interface NetworkInterface
{
    /** Its name in a Registry and in the JSON: "facebook", "x", "copy"... */
    public function name(): string;

    /** The key of its label in the share labels' catalogues (Share/translations/<locale>.php): "omnipost.share.facebook"... */
    public function labelKey(): string;

    /** An icon's name, for the application's own icon set: "facebook", "x", "envelope", "link", "share"... */
    public function icon(): string;

    /** The network's brand colour (#rrggbb), null for the ones without a brand (e-mail, copy, share sheet). */
    public function color(): ?string;

    /**
     * What it takes of a Shareable.
     *
     * @return list<'url'|'title'|'text'|'image'|'hashtags'|'via'>
     */
    public function uses(): array;

    /** Its address for this Shareable: the share page, a mailto:, or the page's own address for copy and share. */
    public function shareUrl(Shareable $shareable): string;

    /**
     * What the share script (assets/omnipost-share.js) does on a click, null for a plain link:
     * "share" (navigator.share), "copy" (the clipboard), "instance" (asks the
     * visitor's Mastodon instance first).
     */
    public function action(): ?string;
}
