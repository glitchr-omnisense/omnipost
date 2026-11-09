<?php

namespace Omnishare;

/**
 * One network's link for one Shareable, with all a template needs to draw it.
 * A web share page opens in a new tab (rel="noopener") - Mastodon's without
 * an instance too, which the script sends to the visitor's own instance; a
 * mailto: and the script's share and copy do not (their address is the page's).
 */
final readonly class Link
{
    public function __construct(
        public string $network,
        public string $url,
        public string $labelKey,
        public string $icon,
        public ?string $color = null,
        public ?string $action = null,
    ) {
    }

    public static function of(NetworkInterface $network, Shareable $shareable): self
    {
        return new self($network->name(), $network->shareUrl($shareable), $network->labelKey(), $network->icon(), $network->color(), $network->action());
    }

    /** True for a share page on the web, which opens in a new tab: no action, or the instance's (Mastodon's share page, wherever the script sends it). */
    public function newTab(): bool
    {
        return \in_array($this->action, [null, 'instance'], true) && 1 === preg_match('~^https?://~i', $this->url);
    }

    /** @return array{network: string, url: string, label: string, icon: string, color: ?string, newTab: bool, action: ?string} */
    public function toArray(): array
    {
        return [
            'network' => $this->network,
            'url' => $this->url,
            'label' => $this->labelKey,
            'icon' => $this->icon,
            'color' => $this->color,
            'newTab' => $this->newTab(),
            'action' => $this->action,
        ];
    }
}
