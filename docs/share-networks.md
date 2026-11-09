---
title: The networks
order: 30
---

# The networks

## The addresses built

For `new Shareable('https://example.com/articles/fuji', 'Mount Fuji at dawn', 'The light at 5 am',
'https://example.com/fuji.jpg', ['#japan', ' travel ', ''], 'example')` - the hashtags trimmed,
their `#` and the empty one dropped - `Registry::defaults()` builds (run in the harness on
2026-10-26):

| Network | Address |
|---|---|
| `facebook` | `https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `x` | `https://twitter.com/intent/tweet?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&text=The%20light%20at%205%20am&hashtags=japan%2Ctravel&via=example` |
| `pinterest` | `https://pinterest.com/pin/create/button/?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&media=https%3A%2F%2Fexample.com%2Ffuji.jpg&description=The%20light%20at%205%20am` |
| `linkedin` | `https://www.linkedin.com/sharing/share-offsite/?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `bluesky` | `https://bsky.app/intent/compose?text=The%20light%20at%205%20am%20%23japan%20%23travel%20https%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `mastodon` | `https://mastodon.social/share?text=The%20light%20at%205%20am%20%23japan%20%23travel%20https%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `threads` | `https://www.threads.com/intent/post?text=The%20light%20at%205%20am%20%23japan%20%23travel&url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `whatsapp` | `https://wa.me/?text=The%20light%20at%205%20am%20https%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `telegram` | `https://t.me/share/url?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&text=The%20light%20at%205%20am` |
| `reddit` | `https://www.reddit.com/submit?url=https%3A%2F%2Fexample.com%2Farticles%2Ffuji&title=Mount%20Fuji%20at%20dawn` |
| `email` | `mailto:?subject=Mount%20Fuji%20at%20dawn&body=The%20light%20at%205%20am%0A%0Ahttps%3A%2F%2Fexample.com%2Farticles%2Ffuji` |
| `copy` | `https://example.com/articles/fuji` |
| `native` | `https://example.com/articles/fuji` |

The message is the text when there is one, else the title (Reddit and the e-mail's subject always
take the title). Bluesky, Mastodon and WhatsApp take one text: the message, then the hashtags as
`#tag` (not WhatsApp), then the address; Threads takes the message and hashtags as `text` and the
address apart. Parameters left empty or null are not written. Mastodon goes to the instance given
to `Registry::defaults($mastodonInstance)`, else to `mastodon.social` with the script's `instance`
action.

## What each network was seen to do

Each address below is what omnishare builds for one page
(`https://example.com/articles/fuji?lang=fr`, "Le Fuji (l'ascension) & nous !", a picture, the
hashtags `japon` and `fuji`, the account `lesringostars`). Each was opened on 2026-10-23 in a
headless Chrome, **signed out**, to see what the network's page receives.

| Network | Address | Seen, signed out |
|---|---|---|
| Facebook | `https://www.facebook.com/sharer/sharer.php?u=…` | "You are not logged in"; the address kept in the page's own address (`u`). Facebook takes the title and picture from the page's og:* tags. **Verified** as far as an anonymous visit goes |
| X | `https://twitter.com/intent/tweet?url=…&text=…&hashtags=japon%2Cfuji&via=…` | twitter.com sends to x.com's sign-in, whose `redirect_after_login` carries `url`, `text`, `hashtags` and `via` intact. **Verified** |
| Pinterest | `https://pinterest.com/pin/create/button/?url=…&media=…&description=…` | www.pinterest.com's "Pin Builder" sign-in, the three parameters kept. **Verified** |
| LinkedIn | `https://www.linkedin.com/sharing/share-offsite/?url=…` | the sign-in, whose `session_redirect` carries the address to `shareArticle`. LinkedIn no longer takes a title or summary in the address. **Verified** |
| Bluesky | `https://bsky.app/intent/compose?text=…` | the app recognises the intent and, signed out, leaves it for its home page (nothing stored). **Verified in Bluesky's source** (below): signed in, the composer opens with `text`; signed out, the intent is dropped and the text is lost - not kept for after the sign-in |
| Mastodon | `https://<instance>/share?text=…` | mastodon.social and piaille.fr answer 302 to their sign-in (a path that does not exist answers 404): the share page is there, behind the sign-in. **Verified in Mastodon's source** (below): the share page is the composer, its text `title`, `text` and `url` joined by spaces |
| Threads | `https://www.threads.com/intent/post?text=…&url=…` | the sign-in, whose `next` carries `text` (with the hashtags) and `url` intact (threads.net answers 301 to threads.com). **Verified** |
| WhatsApp | `https://wa.me/?text=…` | api.whatsapp.com's "Share on WhatsApp" shows "Le Fuji (l'ascension) & nous ! https://example.com/articles/fuji?lang=fr". **Verified** |
| Telegram | `https://t.me/share/url?url=…&text=…` | "Telegram: Share Web Page" shows the address and the title. **Verified** |
| Reddit | `https://www.reddit.com/submit?url=…&title=…` | a "prove your humanity" challenge for a headless browser; the address and the title kept. **Verified** to that point |
| E-mail | `mailto:?subject=…&body=…` | no network: the subject and body decode to the title and the address (a space is `%20`) |
| Copy | the address | `assets/omnishare.js` in a headless Chrome: the address on the clipboard |
| Share sheet | the address | `assets/omnishare.js` in a headless Chrome: `navigator.share` given the title and the address |

## Mastodon and Bluesky, in their source (2026-10-24)

Their composer cannot be seen without an account; what they do with the address was read in their
own code instead:

- **Mastodon** (mastodon/mastodon, branch main at f045f6b43): `config/routes.rb` has
  `resource :share, only: [:show]`; `SharesController` runs `before_action :authenticate_user!`
  (the 302 to the sign-in seen live) and renders `shares/show`, which mounts the standalone
  composer (`entrypoints/share.tsx`). `ApplicationHelper#render_initial_state` gives it
  `text: [params[:title], params[:text], params[:url]].compact.join(' ')`
  (`InitialStateSerializer`: `store[:text]`), and the compose reducer, hydrated, sets the
  composer's text and focuses it. So omnishare's one `text` (message, hashtags, address) is the
  post as it is written; `visibility` (public, unlisted, private, direct) is read too, and not
  given by omnishare. Signed out, Devise's sign-in returns to the address asked by its default
  (`store_location` for a GET page): read, not seen.
- **Bluesky** (bluesky-social/social-app, branch main at 4e4ad9c5f):
  `src/lib/hooks/useIntentHandler.ts` reads `/intent/compose` and its `text` (also `imageUris`
  and `videoUri`, local files of the native app only: an `http(s)` address is filtered out, and the
  web app never takes images). `useComposeIntent` begins with `if (!hasSession) return`: a visitor
  signed in at bsky.app gets the composer with the text; one signed out keeps nothing - the home
  page seen live - and must share again once signed in. Bluesky draws the link's card from the
  address in the text.

Addresses that were not chosen: X's newer `x.com/intent/post` (twitter.com's intent answers 301 to
x.com and keeps every parameter; lesringostars' links used twitter.com before omnishare and are
unchanged); Facebook's `quote` (ignored by Facebook since 2023); LinkedIn's `shareArticle?mini=true`
with `title` and `summary` (LinkedIn sends share-offsite there itself and drops them).
