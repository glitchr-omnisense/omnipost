# omnisense/omnipost

Omnipost is the PHP family for the social networks, with two contracts. **Publishing**: a
site writes one canonical post - a reel, a picture, a carousel - with its caption, its hashtags and
a title for the networks that want one; checks it against what each network accepts before
anything is sent; publishes it on each; follows each publication until it is out; and reads its
own account and feed back for a wall on the site. Each network is a package of its own
(`omnipost/instagram`, `omnipost/youtube`) that turns those calls into the network's API.
**Sharing**: plain share links for a page's visitors - each network's own share page, an e-mail,
a copied link, the device's share sheet - with no network script, no cookie and no credential, and
one JSON for Twig, Blade and React ([Share links](share.md)). The share links came from
`omnisense/omnishare`, merged into this package on 2026-11-09.

```php
$post = Post::reel(Media::video($url, $path, 42.0, 1080, 1920), 'Strauss, tonight.', null, ['violin'])
    ->withVariant(new Variant(Platform::YOUTUBE, title: 'Strauss, Sonata'));

$validator->validate($post->for(Platform::INSTAGRAM), $instagram->capabilities());   // list<Violation>, nothing sent
$publication = $instagram->publish($post->for(Platform::INSTAGRAM));                 // PENDING
$publication = $instagram->status($publication->id);                                 // PROCESSING, then PUBLISHED
$youtube->feed(null, 5)->items;                                                      // the channel's latest videos
$instagram->refresh();                                                               // 60 more days for the token

// use Omnipost\Share;
$share = Share\Registry::defaults()->share(new Share\Shareable($pageUrl, 'Strauss, tonight'), ['facebook', 'x', 'email']);
$share->toArray();                                                                   // the links a visitor shares the page with
```

It is about publishing, reading one's own account and sharing a page, not listening to a catalogue (that is
`omnisense/omnisong`). The tokens stay with the site: a provider is built from the options it is
given, refreshes and exchanges tokens when asked, and hands the `Token` back for the site to store.

The core needs no framework: it requires `symfony/http-client-contracts` and nothing else; each
provider package requires `symfony/http-client`, a library that stands alone. A Symfony bundle is
included for applications that want the wiring done ([Symfony](symfony.md)).

Published as `omnisense/omnipost`, formerly `glitchr/omnipost` and `omniscient/omnipost`: the package
`replace`s both names, so a `composer.json` that still requires one of them resolves to this
package and never installs two. The namespace is `Omnipost\`; the providers keep the `omnipost/`
vendor and the `Omnipost\<Network>\` namespace. `omnisense/omnishare` is not replaced: its
namespace changed (`Omnishare\` is `Omnipost\Share\`), so an application that used it changes its
`use` lines and requires this package instead ([Share links](share.md)).

## When to use it

- A musician's, a venue's or a shop's site that posts the same reel on Instagram and YouTube from
  its back office, with one caption and a variant per network where needed.
- A back office that must tell the editor, before anything is sent, why a network would refuse a
  post (a 16:9 reel on Instagram, a Short of four minutes, a caption too long).
- A wall of the account's latest posts or videos on the site, cached from the account's own feed.

It does not schedule: no network here takes a publication time except YouTube, and a site that
wants "post at 18:00" runs the publication at 18:00 itself (a cron, a delayed message). It does not
store tokens or publications: that is the application's job. It reads one's own account only, not
anyone else's posts, and it does not distribute releases to streaming services
(`DistributorInterface` is declared and implemented by no provider).

## Providers

Two packages. The capabilities are what each provider's code answers (its `capabilities()` and the
interfaces it implements), checked by its tests on recorded answers.

| Package | Name | Publishes | Kinds | Status | Delete | Feed | Token | OAuth |
|---|---|---|---|---|---|---|---|---|
| [`omnipost/instagram`](https://github.com/glitchr-omnisense/omnipost-instagram) | `instagram` | yes, from public URLs | reel, image, carousel (2-10), story | yes, publishes the container when ready | no | yes | long-lived (60 days), `refresh()` | `authorizationUrl()`, `exchange()` |
| [`omnipost/youtube`](https://github.com/glitchr-omnisense/omnipost-youtube) | `youtube` | yes, uploads the file | reel (a Short), video | yes | yes | yes, with a key or the token | access token drawn from a refresh token | `authorizationUrl()`, `exchange()` |

`Platform` lists ten networks (Instagram, YouTube, Facebook, TikTok, Threads, X, LinkedIn,
Bluesky, Mastodon, Pinterest): only the first two have a provider.

## The contracts per network

A provider package, `omnipost/<network>`, implements the contracts that need its network's API:
publishing (`PublisherInterface`), reading the account (`FeedInterface`), tokens
(`Auth\RefreshableInterface`, `Auth\OAuthInterface`). The share contract
(`Share\NetworkInterface`) needs no API: a share network is an address template, so every one of
them is in the core's table (`Share\Registry::defaults()`), and a provider never carries one. A
network that has both keeps its share link in the core and gets publishing from its provider.

| Network | Share link (the core) | Publishing, feed (a provider) |
|---|---|---|
| Instagram | none: no share page on the web | `omnipost/instagram` |
| YouTube | none: no share page on the web | `omnipost/youtube` |
| TikTok | none: no share page on the web | none yet |
| Facebook | `facebook` | none yet (`omnipost/facebook` when a Meta app exists) |
| X, Threads, LinkedIn, Bluesky, Mastodon, Pinterest | `x`, `threads`, `linkedin`, `bluesky`, `mastodon`, `pinterest` | none yet: each has a publishing API, none is written |
| WhatsApp, Telegram, Reddit | `whatsapp`, `telegram`, `reddit` | not in `Platform` |
| E-mail, the clipboard, the device's share sheet | `email`, `copy`, `native` | - |

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP, publishing, tokens.
- [Symfony](symfony.md) and [Laravel](laravel.md): wiring the registry in a framework.
- [Concepts](concepts.md): providers and their interfaces, factories, the registry, every model.
- [Share links](share.md): the share contract - [concepts](share-concepts.md),
  [the networks](share-networks.md), [templates](share-templates.md), [the script](share-javascript.md).
- Usage: [publishing](usage-publishing.md), [validating a post](usage-validation.md),
  [reading an account](usage-reading.md), [tokens and connecting an account](usage-tokens.md).
- [Configuration reference](configuration.md): every option of every factory.
- [Usage policies](policies.md): what each network's terms allow, declared as data, the guard,
  a site's tightening; a post never sent twice.
- [Errors](errors.md): the exceptions and when each is thrown.
- [Testing](testing.md): testing an application that uses Omnipost.
- [The Docker harness](harness.md): every provider installed, a console to call them.
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
