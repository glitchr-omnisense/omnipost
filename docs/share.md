# omnisense/omnishare

Share links to social networks: a page's address, title, text, picture and hashtags turned into
each network's own share page, an e-mail, a copied link and the device's share sheet. The links
are **plain links**: no network's script is loaded, so no cookie is set and nothing needs the
visitor's consent; there is no account, no credential and no API call. Publishing from an
account is another family's job (omnipost).

```php
use Omnishare\Registry;
use Omnishare\Shareable;

$share = Registry::defaults()->share(
    new Shareable('https://example.com/articles/fuji', 'Mount Fuji at dawn', image: 'https://example.com/fuji.jpg', hashtags: ['japan']),
    ['facebook', 'x', 'pinterest', 'email', 'native'],   // the ones the page shows, in its order
);
$share->url('x');     // https://twitter.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&text=Mount%20Fuji%20at%20dawn&hashtags=japan
$share->toArray();    // the one JSON Twig, Blade and React read
```

It requires PHP 8.2 or later and nothing else: no framework, no template engine, no HTTP client.
A small script, `assets/omnishare.js` (no dependency), adds what a link cannot do: the device's
share sheet, copying the address, asking the visitor's Mastodon instance.

## When to use it

- Share buttons on articles, products or events, without embedding each network's widget (and
  its trackers): the buttons stay links, the consent banner has nothing to ask.
- One set of links drawn the same way by a Symfony page, a Laravel twin and a React screen: they
  all read `Share::toArray()`.

Not for posting to an account (omnipost), counting shares (no network gives counts to plain links)
or reading a page's Open Graph tags (the networks read them themselves).

## Networks

All in this package: a network is an address template, with no dependency or credential to
install apart, so a package per network would be thirteen repositories of thirty lines each. An
application adds its own by implementing `NetworkInterface` ([Installation](installation.md#a-network-of-ones-own)).

| Name | Label key | Icon | Colour | Takes | Script action | New tab |
|---|---|---|---|---|---|---|
| `facebook` | `omnishare.facebook` | `facebook` | `#1877f2` | url | - | yes |
| `x` | `omnishare.x` | `x` | `#000000` | url, title, text, hashtags, via | - | yes |
| `pinterest` | `omnishare.pinterest` | `pinterest` | `#e60023` | url, image, title, text | - | yes |
| `linkedin` | `omnishare.linkedin` | `linkedin` | `#0a66c2` | url | - | yes |
| `bluesky` | `omnishare.bluesky` | `bluesky` | `#1185fe` | url, title, text, hashtags | - | yes |
| `mastodon` | `omnishare.mastodon` | `mastodon` | `#6364ff` | url, title, text, hashtags | `instance` when no instance is configured | yes |
| `threads` | `omnishare.threads` | `threads` | `#000000` | url, title, text, hashtags | - | yes |
| `whatsapp` | `omnishare.whatsapp` | `whatsapp` | `#25d366` | url, title, text | - | yes |
| `telegram` | `omnishare.telegram` | `telegram` | `#26a5e4` | url, title, text | - | yes |
| `reddit` | `omnishare.reddit` | `reddit` | `#ff4500` | url, title | - | yes |
| `email` | `omnishare.email` | `envelope` | - | url, title, text | - | no |
| `copy` | `omnishare.copy` | `link` | - | url | `copy` | no |
| `native` | `omnishare.native` | `share` | - | url, title, text | `share` | no |

"Takes" is what `NetworkInterface::uses()` declares; "New tab" is `Link::newTab()`, true for a
share page on the web with no script action or with Mastodon's `instance`. The address each one builds is in
[The networks](networks.md#the-addresses-built), with what each network was seen to do with it.

## How this manual is organised

- [Installation](installation.md): plain PHP, a network of one's own.
- [Concepts](concepts.md): `Shareable`, `Registry`, `Share`, `Link`, `NetworkInterface`, the labels.
- [The networks](networks.md): every address, and how each was checked.
- [Templates](templates.md): Twig, Blade and React, one JSON.
- [The script](javascript.md): share sheet, copy, Mastodon instance.
- [Errors](errors.md) and [testing](testing.md).
- [The Docker harness](harness.md).
- [Verified, and not](verified.md).
