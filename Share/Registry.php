<?php

namespace Omnishare;

use Omnishare\Exception\UnknownNetworkException;

/**
 * The networks by name, and the links of a Shareable to those an application
 * shows, in its order.
 *
 *     $registry = Registry::defaults();
 *     $share = $registry->share(new Shareable($url, $title, image: $picture), ['facebook', 'x', 'pinterest', 'email']);
 *     $share->url('x');      // https://twitter.com/intent/tweet?url=...&text=...
 *     $share->toArray();     // the JSON a template reads
 */
final class Registry
{
    /** @var array<string, NetworkInterface> */
    private array $networks = [];

    /** @param iterable<NetworkInterface> $networks */
    public function __construct(iterable $networks = [])
    {
        foreach ($networks as $network) {
            $this->add($network);
        }
    }

    /**
     * Every network omnishare knows, in this order: facebook, x, pinterest,
     * linkedin, bluesky, mastodon, threads, whatsapp, telegram, reddit, email,
     * copy, native.
     *
     * @param string|null $mastodonInstance the instance Mastodon's link goes to (e.g. "mastodon.social"); null lets the visitor give theirs
     */
    public static function defaults(?string $mastodonInstance = null): self
    {
        return new self([
            new Network\Facebook(),
            new Network\X(),
            new Network\Pinterest(),
            new Network\LinkedIn(),
            new Network\Bluesky(),
            new Network\Mastodon($mastodonInstance),
            new Network\Threads(),
            new Network\WhatsApp(),
            new Network\Telegram(),
            new Network\Reddit(),
            new Network\Email(),
            new Network\CopyLink(),
            new Network\Native(),
        ]);
    }

    /** Adds a network, or replaces the one of the same name. */
    public function add(NetworkInterface $network): self
    {
        $this->networks[$network->name()] = $network;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->networks[$name]);
    }

    public function get(string $name): NetworkInterface
    {
        return $this->networks[$name] ?? throw new UnknownNetworkException(\sprintf('No share network "%s"; known: %s.', $name, implode(', ', $this->names())));
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->networks);
    }

    /**
     * The links of a Shareable.
     *
     * @param list<string>|null $networks the names, in the order they are shown; null for every network
     */
    public function share(Shareable $shareable, ?array $networks = null): Share
    {
        $links = [];
        foreach ($networks ?? $this->names() as $name) {
            $links[$name] = Link::of($this->get($name), $shareable);
        }

        return new Share($shareable, $links);
    }
}
