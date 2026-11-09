// omnishare.js on node's own test runner, with small stand-ins for the DOM:
//   node --test Tests/js/omnishare.test.cjs
const test = require('node:test');
const assert = require('node:assert/strict');
const Omnishare = require('../../assets/omnishare.js');

function element(attributes = {}, parent = null) {
    const attrs = { ...attributes };
    const el = {
        hidden: 'hidden' in attrs,
        parent,
        events: [],
        getAttribute: (name) => (name in attrs ? attrs[name] : null),
        setAttribute: (name, value) => { attrs[name] = value; },
        removeAttribute: (name) => { delete attrs[name]; },
        hasAttribute: (name) => name in attrs,
        dispatchEvent: (event) => { el.events.push(event.type); return true; },
        closest(selector) {
            const name = selector.slice(1, -1);
            for (let node = el; node; node = node.parent) if (node.getAttribute(name) !== null) return node;
            return null;
        },
    };
    return el;
}
const rootOf = (links) => ({ querySelectorAll: () => links });
const click = (target) => { const event = { target, prevented: false, preventDefault() { event.prevented = true; } }; return event; };

test('the share sheet and the copy are shown only where the browser has them', () => {
    const item = element({ 'data-omnishare-item': '', hidden: '' });
    const share = element({ 'data-omnishare': 'share' }, item);
    const copy = element({ 'data-omnishare': 'copy', hidden: '' });
    Omnishare.reveal(rootOf([share, copy]), {});
    assert.equal(item.hidden, true, 'no navigator.share: its item stays hidden');
    assert.equal(copy.hidden, true, 'no clipboard: hidden');

    Omnishare.reveal(rootOf([share, copy]), { share() {}, clipboard: { writeText() {} } });
    assert.equal(item.hidden, false);
    assert.equal(copy.hidden, false);
});

test('a click on the share action gives the sheet its title, text and address', async () => {
    let shared = null;
    const link = element({ 'data-omnishare': 'share', href: 'https://example.com/a', 'data-omnishare-title': 'Le Fuji', 'data-omnishare-text': '' });
    const event = click(link);
    await Omnishare.handle(event, { share: (data) => { shared = data; return Promise.reject(new Error('AbortError')); } }, {});
    assert.equal(event.prevented, true);
    assert.deepEqual(shared, { url: 'https://example.com/a', title: 'Le Fuji' }, 'an empty text is left out; a closed sheet says nothing');
});

test('a click on copy writes the address, marks the link and tells the page', async () => {
    let written = null; let timer = null;
    const link = element({ 'data-omnishare': 'copy', href: 'https://example.com/a' });
    const icon = element({}, link);
    global.CustomEvent = class { constructor(type) { this.type = type; } };
    await Omnishare.handle(click(icon), { clipboard: { writeText: async (text) => { written = text; } } }, { setTimeout: (f) => { timer = f; } });
    assert.equal(written, 'https://example.com/a');
    assert.equal(link.hasAttribute('data-omnishare-copied'), true);
    assert.deepEqual(link.events, ['omnishare:copied']);
    timer();
    assert.equal(link.hasAttribute('data-omnishare-copied'), false, 'two seconds later the mark is gone');
});

test('Mastodon asks the visitor\'s instance and opens the same share page there', () => {
    const href = 'https://mastodon.social/share?text=Le%20Fuji%20https%3A%2F%2Fexample.com%2Fa';
    const link = element({ 'data-omnishare': 'instance', href, 'data-omnishare-prompt': 'Votre instance' });
    let asked = null; let opened = null;
    const host = Omnishare.handle(click(link), {}, { prompt: (q, d) => { asked = [q, d]; return '@marco@Piaille.fr'; }, open: (...a) => { opened = a; } });
    assert.deepEqual(asked, ['Votre instance', 'mastodon.social']);
    assert.equal(host, 'piaille.fr');
    assert.deepEqual(opened, ['https://piaille.fr/share?text=Le%20Fuji%20https%3A%2F%2Fexample.com%2Fa', '_blank', 'noopener']);

    const cancelled = element({ 'data-omnishare': 'instance', href });
    assert.equal(Omnishare.handle(click(cancelled), {}, { prompt: () => null, open: () => assert.fail('nothing opened') }), null);
});

test('an instance is read from what a visitor types', () => {
    assert.equal(Omnishare.instance(' https://Piaille.fr/about '), 'piaille.fr');
    assert.equal(Omnishare.instance('@me@mastodon.social'), 'mastodon.social');
    assert.equal(Omnishare.instance('localhost'), null);
    assert.equal(Omnishare.instance('not a host'), null);
});

test('a plain link is left to the browser', () => {
    const link = element({ 'data-omnishare': '', href: 'https://www.facebook.com/sharer/sharer.php?u=x' });
    const event = click(element({}, null));
    assert.equal(Omnishare.handle(event, {}, {}), false);
    assert.equal(event.prevented, false);
    assert.equal(Omnishare.handle(click(link), {}, {}), false);
});
