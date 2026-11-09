---
title: Concepts
order: 20
---

# Concepts

## Shareable

`Omnishare\Shareable` (final, readonly) is what is shared: a page, by its canonical address, with
what the networks show of it.

| Property | Type | Default | |
|---|---|---|---|
| `url` | `string` | - | the page's canonical address, whole (http or https scheme and a host) |
| `title` | `string` | - | the page's title: the e-mail's subject, Reddit's title, the message when there is no text |
| `text` | `?string` | `null` | a sentence posted with the link instead of the title |
| `image` | `?string` | `null` | the page's share picture, whole (Pinterest's pin); `''` is accepted as none |
| `hashtags` | `list<string>` | `[]` | each trimmed, its leading `#` removed, the empty ones dropped |
| `via` | `?string` | `null` | an account to credit, without its `@` (X's `via`) |

The constructor throws `InvalidShareableException` when `url`, or a non-empty `image`, has no
`http`/`https` scheme or no host: a share page opens on another site, where a path means nothing.

| Method | |
|---|---|
| `message(): string` | the text when there is one (not null, not `''`), else the title |
| `toArray(): array` | `url`, `title`, `text`, `image`, `hashtags`, `via` |

## Registry

`Omnishare\Registry` holds the networks by name.

| Method | |
|---|---|
| `new Registry(iterable $networks = [])` | a registry of those networks |
| `Registry::defaults(?string $mastodonInstance = null)` | the 13 networks, in the order of the [table](index.md#networks); Mastodon's link goes to `$mastodonInstance`, or is asked of the visitor when `null` |
| `add(NetworkInterface $network): self` | adds a network, or replaces the one of the same name |
| `has(string $name): bool` | |
| `get(string $name): NetworkInterface` | `UnknownNetworkException` for a name it does not hold, listing the known ones |
| `names(): list<string>` | in the order they were added |
| `share(Shareable $shareable, ?array $networks = null): Share` | the links to those networks, in the order given; `null`: every network |

## Share and Link

`Omnishare\Share` (final, readonly, `JsonSerializable`) is a `Shareable` and its links, keyed by
network name in the order asked for: what a template draws.

| Member | |
|---|---|
| `shareable` | the `Shareable` |
| `links` | `array<string, Link>` |
| `link(string $network): ?Link` | `null` when it was not asked for |
| `url(string $network): ?string` | that link's address |
| `toArray()`, `jsonSerialize()` | the Shareable's array plus `links`: each link's array |

`Omnishare\Link` (final, readonly) is one network's link for one Shareable:

| Property or method | Type | |
|---|---|---|
| `network` | `string` | the network's name |
| `url` | `string` | the share page, a `mailto:`, or the page's own address (copy, share sheet) |
| `labelKey` | `string` | `omnishare.<name>`, a key of the labels below; `label` in the array |
| `icon` | `string` | a name for the application's icon set |
| `color` | `?string` | the brand colour, `#rrggbb`, `null` without a brand |
| `action` | `?string` | what the script does on a click: `share`, `copy`, `instance`; `null` for a plain link |
| `newTab(): bool` | | true when the address is http(s) and there is no action, or the action is `instance` (Mastodon's share page, the script's markup has `target="_blank"`): open it in a new tab, `rel="noopener"`; false for a `mailto:`, `share` and `copy` |
| `Link::of(NetworkInterface, Shareable)` | | builds one |
| `toArray()` | | `network`, `url`, `label`, `icon`, `color`, `newTab`, `action` |

## NetworkInterface and AbstractNetwork

`Omnishare\NetworkInterface` is a place a page can be shared to. A network builds an address from
a Shareable and says how it is shown; it calls nothing and holds no credential.

| Method | |
|---|---|
| `name(): string` | its name in a registry and in the JSON |
| `labelKey(): string` | the key of its label |
| `icon(): string` | an icon's name |
| `color(): ?string` | its brand colour, or `null` |
| `uses(): list<'url'\|'title'\|'text'\|'image'\|'hashtags'\|'via'>` | what it takes of a Shareable (informative: the address is what `shareUrl()` builds) |
| `shareUrl(Shareable): string` | its address for this Shareable |
| `action(): ?string` | the script's action, or `null` |

`Omnishare\Network\AbstractNetwork` implements all but `name()`, `uses()` and `shareUrl()`: label key
`omnishare.<name>`, icon `<name>`, no colour, no action. Two helpers for subclasses:

- `query(array $parameters): string` - a query string encoded as RFC 3986 says (`rawurlencode`: a
  space is `%20`, never `+`, which a mail client would show as it is), in the order given, the null
  and empty values left out;
- `post(Shareable $shareable, bool $hashtags = true): string` - the message, the hashtags as `#tag`,
  and the address, joined by spaces: for the networks that take a single text.

`Omnishare\Network\Mastodon` takes the instance in its constructor: a host (`piaille.fr`) or an
address whose host is taken (`https://Piaille.fr/@someone` gives `piaille.fr`); `instance()`
answers it, `null` when none was given (the link then goes to `Mastodon::FALLBACK`,
`mastodon.social`, and the script asks the visitor).

## Labels

`Omnishare\Labels` holds omnishare's own labels, in English and French (`translations/en.php`,
`translations/fr.php`, plain PHP arrays), for an application without words of its own.

| Method | |
|---|---|
| `Labels::locales()` | `['en', 'fr']` |
| `Labels::catalogue(string $locale)` | the labels of a locale (`fr`, `fr_FR`, `fr-CA`: the first two letters), English for any other |
| `Labels::get(string $key, string $locale = 'en')` | a label, the key itself when there is none |

The keys: `omnishare.title` (the list's name, "Share this page"), one per network
(`omnishare.facebook`...), `omnishare.mastodon.instance` (the question the script asks),
`omnishare.copied` (to say the address was copied). An application with a translator imports the
two files into its catalogues, or keeps its own keys.
