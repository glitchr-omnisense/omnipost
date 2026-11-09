---
title: The share script
order: 39
---

# The share script: share sheet, copy, Mastodon instance

`assets/omnipost-share.js` (no dependency, ES5, 6 KB with its comments) does what a plain link cannot, only where
the browser can:

| `data-omnipost-share` | On a click | Shown |
|---|---|---|
| `share` | `navigator.share({title, text, url})` from `data-omnipost-share-title`, `data-omnipost-share-text` and `data-omnipost-share-url` (the `href` by default) | only where `navigator.share` exists |
| `copy` | the address on the clipboard; `data-omnipost-share-copied` on the link for two seconds and an `omnipost-share:copied` event (bubbling, `detail.url`) for the page to say so | only where `navigator.clipboard.writeText` exists |
| `instance` | asks the visitor's Mastodon instance (`prompt()`, with `data-omnipost-share-prompt` as the question and the link's instance as the answer offered), then opens the same share page there | always (without the script, the link goes to its own instance) |

Mark the hidden ones `hidden`; the attribute may sit on an ancestor marked `data-omnipost-share-item`
(a list item) instead of the link. Nothing is stored: not the instance, not anything else.

```html
<script src="/vendor/omnipost/omnipost-share.js" defer></script>
```

Loaded as a script it starts itself: one click listener on the document (so links added later
work), and the reveal at `DOMContentLoaded` and at every `load` - libraries that swap pages in
dispatch `load` again. `OmnipostShare.reveal(element)` reveals what is under an element added since.
Required from CommonJS (a bundler, from `vendor/omnisense/omnipost/assets/omnipost-share.js`) it only
exports `reveal`, `handle`, `instance`, `onInstance`, `available` and `start`.
