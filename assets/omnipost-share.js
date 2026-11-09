/*!
 * omnishare.js - the share actions a plain link cannot do, added where the
 * browser can: the device's share sheet (navigator.share), the page's address
 * copied (the clipboard), the visitor's own Mastodon instance. No dependency,
 * no network script, no cookie, nothing stored. MIT, omnisense/omnishare.
 *
 * The markup (Share::toArray()'s links, any template engine):
 *
 *   <a href="{url}" data-omnishare="share" data-omnishare-title="{title}" data-omnishare-text="{text}" hidden>...</a>
 *   <a href="{url}" data-omnishare="copy" hidden>...</a>
 *   <a href="{mastodon url}" data-omnishare="instance" data-omnishare-prompt="{label}" target="_blank" rel="noopener">...</a>
 *
 * "share" and "copy" stay hidden where the browser lacks them; the attribute
 * may sit on an ancestor marked data-omnishare-item instead (a list item).
 * "instance" asks the visitor's instance and opens its share page; without
 * this script the link goes to the instance in its href. A copy sets
 * data-omnishare-copied on the link for two seconds and dispatches
 * "omnishare:copied" (bubbling) for the page to say so.
 *
 * Loaded as a script it starts itself (DOMContentLoaded and every "load",
 * which page-swapping libraries dispatch again); Omnishare.reveal(root) does
 * it again for markup added later. As a CommonJS module it only exports.
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.Omnishare = factory();
        root.Omnishare.start();
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var started = false;

    function available(action, nav) {
        if (action === 'share') return !!nav && typeof nav.share === 'function';
        if (action === 'copy') return !!nav && !!nav.clipboard && typeof nav.clipboard.writeText === 'function';
        return true;
    }

    /** Shows the actions this browser has, under root (the document by default). */
    function reveal(root, nav) {
        root = root || document;
        nav = nav || (typeof navigator !== 'undefined' ? navigator : null);
        var links = root.querySelectorAll('[data-omnishare]');
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            if (!available(link.getAttribute('data-omnishare'), nav)) continue;
            if (link.hidden) link.hidden = false;
            var item = typeof link.closest === 'function' ? link.closest('[data-omnishare-item]') : null;
            if (item && item.hidden) item.hidden = false;
        }
    }

    /** "@me@piaille.fr", "https://piaille.fr/about", " Piaille.fr " -> "piaille.fr"; null when it is no host. */
    function instance(answer) {
        var host = String(answer || '').trim().replace(/^@?[^@\s\/]+@/, '').replace(/^[a-z][a-z0-9+.-]*:\/\//i, '').split(/[\/?#]/)[0].toLowerCase();
        return /^[a-z0-9-]+(\.[a-z0-9-]+)+(:\d+)?$/.test(host) ? host : null;
    }

    /** The same share page on another instance. */
    function onInstance(href, host) {
        var url = new URL(href);
        url.host = host;
        return url.href;
    }

    /** What a click on a share action does; false when it was none (a plain link, left to the browser). */
    function handle(event, nav, win) {
        var target = event.target;
        var link = target && typeof target.closest === 'function' ? target.closest('[data-omnishare]') : null;
        if (!link) return false;
        var action = link.getAttribute('data-omnishare');
        var url = link.getAttribute('data-omnishare-url') || link.getAttribute('href');
        if (!available(action, nav)) return false;

        if (action === 'share') {
            event.preventDefault();
            var data = { url: url };
            var title = link.getAttribute('data-omnishare-title');
            var text = link.getAttribute('data-omnishare-text');
            if (title) data.title = title;
            if (text) data.text = text;
            // A sheet closed without sharing rejects (AbortError): nothing to say.
            return Promise.resolve(nav.share(data)).catch(function () {});
        }
        if (action === 'copy') {
            event.preventDefault();
            return Promise.resolve(nav.clipboard.writeText(url)).then(function () {
                link.setAttribute('data-omnishare-copied', '');
                if (typeof link.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
                    link.dispatchEvent(new CustomEvent('omnishare:copied', { bubbles: true, detail: { url: url } }));
                }
                (win && win.setTimeout ? win.setTimeout : setTimeout)(function () { link.removeAttribute('data-omnishare-copied'); }, 2000);
            }, function () {});
        }
        if (action === 'instance') {
            event.preventDefault();
            var current = new URL(link.getAttribute('href')).host;
            var answer = win.prompt(link.getAttribute('data-omnishare-prompt') || 'Mastodon', current);
            var host = instance(answer);
            if (!host) return null;
            win.open(onInstance(link.getAttribute('href'), host), '_blank', 'noopener');
            return host;
        }
        return false;
    }

    /** Listens once on the document and reveals what the browser has, now and at every "load". */
    function start() {
        if (started || typeof document === 'undefined') return;
        started = true;
        document.addEventListener('click', function (event) { handle(event, navigator, window); });
        var again = function () { reveal(document, navigator); };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', again);
        else again();
        window.addEventListener('load', again);
    }

    return { reveal: reveal, handle: handle, instance: instance, onInstance: onInstance, available: available, start: start };
});
