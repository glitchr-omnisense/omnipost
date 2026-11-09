---
title: Templates
order: 35
---

# Templates: Twig, Blade, React - one JSON

`Share::toArray()` is the same array whatever draws it:

```json
{
  "url": "https://example.com/articles/fuji", "title": "Mount Fuji at dawn", "text": null,
  "image": "https://example.com/images/fuji.jpg", "hashtags": ["japan"], "via": null,
  "links": {
    "facebook": {"network": "facebook", "url": "https://www.facebook.com/sharer/sharer.php?u=…", "label": "omnishare.facebook",
                 "icon": "facebook", "color": "#1877f2", "newTab": true, "action": null},
    "email":    {"network": "email", "url": "mailto:?subject=…&body=…", "label": "omnishare.email",
                 "icon": "envelope", "color": null, "newTab": false, "action": null},
    "native":   {"network": "native", "url": "https://example.com/articles/fuji", "label": "omnishare.native",
                 "icon": "share", "color": null, "newTab": false, "action": "share"}
  }
}
```

`icon` is a name for the application's own icon set (Font Awesome: `fa-brands fa-<icon>`, with
`fa-solid` for `envelope`, `link` and `share` - `share-nodes` in Font Awesome 6). `label` is a key
of omnishare's catalogues; an application may use its own words instead.

## Twig

```twig
<ul class="share" aria-label="{{ 'omnishare.title'|trans }}">
  {% for link in share.links %}
    <li{% if link.action in ['share', 'copy'] %} data-omnishare-item hidden{% endif %}>
      <a href="{{ link.url }}"{% if link.newTab %} target="_blank" rel="noopener"{% endif %}
         {% if link.action %}data-omnishare="{{ link.action }}" data-omnishare-title="{{ share.title }}"{% endif %}
         aria-label="{{ link.label|trans }}" style="--share-color: {{ link.color ?? 'currentColor' }}">
        <i class="fa-{{ link.icon in ['envelope', 'link', 'share'] ? 'solid' : 'brands' }} fa-{{ link.icon }}" aria-hidden="true"></i>
      </a>
    </li>
  {% endfor %}
</ul>
```

`share` is `$share->toArray()` given to the template (a Twig function of the application's, or a
controller's variable).

## Blade

The same loop in Blade, `$share` the array:

```blade
<ul class="share" aria-label="{{ __('omnishare.title') }}">
  @foreach ($share['links'] as $link)
    <li @if (in_array($link['action'], ['share', 'copy'])) data-omnishare-item hidden @endif>
      <a href="{{ $link['url'] }}" @if ($link['newTab']) target="_blank" rel="noopener" @endif
         @if ($link['action']) data-omnishare="{{ $link['action'] }}" data-omnishare-title="{{ $share['title'] }}" @endif
         aria-label="{{ __($link['label']) }}">…</a>
    </li>
  @endforeach
</ul>
```

## React

The screen's props carry the same array (`share: ShareJson`); the share sheet and the copy are
the component's own state rather than the script's:

```tsx
type Link = { network: string; url: string; label: string; icon: string; color: string | null; newTab: boolean; action: string | null };
type ShareJson = { url: string; title: string; text: string | null; image: string | null; hashtags: string[]; via: string | null; links: Record<string, Link> };

function Share({ share, t }: { share: ShareJson; t: (key: string) => string }) {
  const [native, setNative] = useState(false);
  useEffect(() => setNative(typeof navigator.share === 'function'), []);
  return (
    <ul className="share">
      {Object.values(share.links).map((link) => link.action === 'share'
        ? <li key={link.network} hidden={!native}><button type="button" onClick={() => navigator.share({ title: share.title, url: share.url }).catch(() => {})} aria-label={t(link.label)} /></li>
        : <li key={link.network}><a href={link.url} {...(link.newTab ? { target: '_blank', rel: 'noopener' } : {})} aria-label={t(link.label)} /></li>)}
    </ul>
  );
}
```
