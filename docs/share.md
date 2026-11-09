---
title: Share links
order: 35
---

# Share links

Omnipost's second contract, beside publishing: share links for a page's visitors. A page's
address, title, text, picture and hashtags turned into each network's own share page, an e-mail, a
copied link and the device's share sheet. The links are **plain links**: no network's script is
loaded, so no cookie is set and nothing needs the visitor's consent; there is no account, no
credential and no API call. Publishing from an account is the other contract
(`PublisherInterface`, [Publishing](usage-publishing.md)).

```php
use Omnipost\Share\Registry;
use Omnipost\Share\Shareable;

$share = Registry::defaults()->share(
    new Shareable('https://example.com/articles/fuji', 'Mount Fuji at dawn', image: 'https://example.com/fuji.jpg', hashtags: ['japan']),
    ['facebook', 'x', 'pinterest', 'email', 'native'],   // the ones the page shows, in its order
);
$share->url('x');     // https://twitter.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&text=Mount%20Fuji%20at%20dawn&hashtags=japan
$share->toArray();    // the one JSON Twig, Blade and React read
```

The share links need PHP 8.2 or later and nothing else: no framework, no template engine, no HTTP
client - `Omnipost\Share` loads no class outside itself (`Tests/Share/BareTest`). A small script,
`assets/omnipost-share.js` (no dependency), adds what a link cannot do: the device's share sheet,
copying the address, asking the visitor's Mastodon instance.

They come from `omnisense/omnishare`, merged into this package on 2026-11-09 with its history:
`Omnishare\` is `Omnipost\Share\` (its two exceptions `Omnipost\Exception\`), the label keys
`omnishare.*` are `omnipost.share.*`, the script `assets/omnishare.js` is
`assets/omnipost-share.js` (`data-omnipost-share`, `OmnipostShare`, `omnipost-share:copied`).

## When to use it

- Share buttons on articles, products or events, without embedding each network's widget (and
  its trackers): the buttons stay links, the consent banner has nothing to ask.
- One set of links drawn the same way by a Symfony page, a Laravel twin and a React screen: they
  all read `Share::toArray()`.

Not for posting to an account (`PublisherInterface`), counting shares (no network gives counts to
plain links) or reading a page's Open Graph tags (the networks read them themselves).

## Networks

All in this package, not in the `omnipost/<network>` providers: a share network is an address
template, with no dependency, option or credential, so a provider package for each would be
thirteen repositories of thirty lines each. A provider package is there for what needs an API
(publishing, reading a feed, a token). An application, or a provider package, adds its own share
network by implementing `NetworkInterface` ([below](#a-network-of-ones-own)).

| Name | Label key | Icon | Colour | Takes | Script action | New tab |
|---|---|---|---|---|---|---|
| `facebook` | `omnipost.share.facebook` | `facebook` | `#1877f2` | url | - | yes |
| `x` | `omnipost.share.x` | `x` | `#000000` | url, title, text, hashtags, via | - | yes |
| `pinterest` | `omnipost.share.pinterest` | `pinterest` | `#e60023` | url, image, title, text | - | yes |
| `linkedin` | `omnipost.share.linkedin` | `linkedin` | `#0a66c2` | url | - | yes |
| `bluesky` | `omnipost.share.bluesky` | `bluesky` | `#1185fe` | url, title, text, hashtags | - | yes |
| `mastodon` | `omnipost.share.mastodon` | `mastodon` | `#6364ff` | url, title, text, hashtags | `instance` when no instance is configured | yes |
| `threads` | `omnipost.share.threads` | `threads` | `#000000` | url, title, text, hashtags | - | yes |
| `whatsapp` | `omnipost.share.whatsapp` | `whatsapp` | `#25d366` | url, title, text | - | yes |
| `telegram` | `omnipost.share.telegram` | `telegram` | `#26a5e4` | url, title, text | - | yes |
| `reddit` | `omnipost.share.reddit` | `reddit` | `#ff4500` | url, title | - | yes |
| `email` | `omnipost.share.email` | `envelope` | - | url, title, text | - | no |
| `copy` | `omnipost.share.copy` | `link` | - | url | `copy` | no |
| `native` | `omnipost.share.native` | `share` | - | url, title, text | `share` | no |

"Takes" is what `NetworkInterface::uses()` declares; "New tab" is `Link::newTab()`, true for a
share page on the web with no script action or with Mastodon's `instance`. The address each one
builds is in [The networks](share-networks.md#the-addresses-built), with what each network was seen
to do with it.

Instagram, YouTube and TikTok have no share page on the web - nothing a link can open with a
page's address filled in - so they are not here: they are reached by publishing
([the contracts per network](index.md#the-contracts-per-network)).

## Installation and use, in plain PHP

```sh
composer require omnisense/omnipost
```

```php
<?php
require 'vendor/autoload.php';

use Omnipost\Share\Labels;
use Omnipost\Share\Registry;
use Omnipost\Share\Shareable;

$registry = Registry::defaults(mastodonInstance: null);   // null: the visitor gives their instance

$page = new Shareable(
    url: 'https://example.com/articles/fuji',         // whole: scheme and host
    title: 'Mount Fuji at dawn',
    text: null,                                      // posted instead of the title when given
    image: 'https://example.com/images/fuji.jpg',    // whole as well (Pinterest)
    hashtags: ['japan', 'travel'],
    via: 'example',                                  // X's "via @example"
);

$share = $registry->share($page, ['facebook', 'x', 'pinterest', 'email', 'copy', 'native']);

foreach ($share->links as $link) {
    printf(
        '<a href="%s"%s data-omnipost-share="%s">%s</a>'."\n",
        htmlspecialchars($link->url),
        $link->newTab() ? ' target="_blank" rel="noopener"' : '',
        htmlspecialchars((string) $link->action),
        htmlspecialchars(Labels::get($link->labelKey, 'en')),
    );
}
```

- `Shareable` checks that its address and its picture are whole http(s) addresses
  (`InvalidShareableException` otherwise): a share page opens on another site, where a path means
  nothing.
- `Registry::share()` takes the networks' names in the order the page shows them; `null` gives
  every network. An unknown name throws `UnknownNetworkException`.
- `Share::url('x')`, `Share::link('x')`, `Share::toArray()` (also `json_encode($share)`).
- `Labels::get($key, $locale)`: the share links' own labels in English and French
  (`Share/translations/<locale>.php`, plain PHP arrays), for an application without its own words.
  An application with a translator imports those files into it, or keeps its own keys.

The Symfony bundle does not wire the share links: `Registry::defaults()` is a plain call, made
where the links are drawn (a Twig function of the application's, a controller).

## A network of one's own

```php
use Omnipost\Share\Network\AbstractNetwork;
use Omnipost\Share\Shareable;

final class Hatena extends AbstractNetwork
{
    public function name(): string { return 'hatena'; }
    public function uses(): array { return ['url', 'title']; }
    public function shareUrl(Shareable $shareable): string
    {
        return 'https://b.hatena.ne.jp/entry/panel/?'.self::query(['url' => $shareable->url, 'title' => $shareable->title]);
    }
}

$registry->add(new Hatena());   // a network of the same name is replaced
```

Its label key is `omnipost.share.hatena` (`AbstractNetwork::labelKey()`): an application gives it
its words, or overrides `labelKey()`.

## The pages on share links

- [Concepts](share-concepts.md): `Shareable`, `Registry`, `Share`, `Link`, `NetworkInterface`, the labels.
- [The networks](share-networks.md): every address, and how each was checked.
- [Templates](share-templates.md): Twig, Blade and React, one JSON.
- [The script](share-javascript.md): share sheet, copy, Mastodon instance.
- [Errors](errors.md#share-links), [testing](testing.md#share-links), [the harness](harness.md) and
  [what is verified](verified.md#share-links).
