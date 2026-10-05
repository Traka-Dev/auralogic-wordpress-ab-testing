const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('assets/tracking.js', 'utf8');
const tick = () => new Promise((resolve) => setImmediate(resolve));
function browser({ path = '/entry/', consent = true, storage = new Map(), fail = false, variant = 'a', initial = null, goal = 'page', visibility = 'visible' } = {}) {
  const listeners = {}, documentListeners = {}, requests = [], redirects = [];
  const experiment = { id: 1, revision: 1, entry: 'https://site.test/entry/', a: 'https://site.test/a/', b: 'https://site.test/b/', thankYou: 'https://site.test/thanks/', goal: 'page', selector: '', consent };
  experiment.goal = goal;
  if (goal === 'click') experiment.selector = '.ab-cta';
  const assignment = { token: 'signed', variant, expires: Math.floor(Date.now() / 1000) + 1000, url: experiment[variant] };
  if (initial) storage.set('bat:1:1', JSON.stringify({ ...assignment, ...initial }));
  const window = { BAT_CONFIG: { endpoint: 'https://site.test/wp-json/builder-ab/v1/', experiments: [experiment] }, addEventListener: (name, fn) => { listeners[name] = fn; } };
  const context = {
    window, navigator: { userAgent: 'Firefox' }, URL, Element: class {},
    location: { href: `https://site.test${path}`, origin: 'https://site.test', replace: (url) => redirects.push(url) },
    document: { visibilityState: visibility, readyState: 'complete', addEventListener: (name, fn) => { documentListeners[name] = fn; }, querySelector: () => null },
    localStorage: { getItem: (key) => storage.get(key), setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
    fetch: async (url, options) => {
      requests.push({ url, data: JSON.parse(options.body) });
      if (fail) throw new Error('offline');
      return { ok: true, json: async () => url.endsWith('assign') ? assignment : { recorded: true } };
    },
  };
  vm.runInNewContext(source, context);
  return { window, listeners, documentListeners, requests, redirects, storage, context };
}
test('consent gate waits, assigns, and preserves campaign parameters', async () => {
  const b = browser({ path: '/entry/?utm_source=mail' });
  await tick(); assert.equal(b.requests.length, 0);
  b.window.BAT_CONSENT = true; b.listeners['bat:consent']();
  await tick(); assert.equal(b.requests.length, 1);
  assert.equal(b.redirects[0], 'https://site.test/a/?utm_source=mail');
  assert.ok(b.storage.has('bat:1:1'));
});
test('direct variant visits without assignment are not counted', async () => {
  const b = browser({ path: '/a/', consent: false }); await tick(); assert.equal(b.requests.length, 0);
});
test('assigned variant records exposure but the other variant does not', async () => {
  const a = browser({ path: '/a/', consent: false, initial: {} });
  const b = browser({ path: '/b/', consent: false, initial: {} });
  await tick(); assert.equal(a.requests[0].data.type, 'exposure'); assert.equal(b.requests.length, 0);
});
test('thank-you page records conversion with assigned token', async () => {
  const b = browser({ path: '/thanks/', consent: false, initial: {} }); await tick();
  assert.equal(b.requests[0].data.type, 'conversion'); assert.equal(b.requests[0].data.token, 'signed');
});
test('REST failures leave entry page usable', async () => {
  const b = browser({ consent: false, fail: true }); await tick(); assert.equal(b.redirects.length, 0);
});
test('revoking consent clears assignment', async () => {
  const b = browser({ initial: {} }); b.window.BAT_CONSENT = false; b.listeners['bat:consent'](); await tick();
  assert.equal(b.storage.has('bat:1:1'), false); assert.equal(b.requests.length, 0);
});
test('hidden variants wait until visible to record exposure', async () => {
  const b = browser({ path: '/a/', consent: false, initial: {}, visibility: 'hidden' });
  await tick(); assert.equal(b.requests.length, 0);
  b.context.document.visibilityState = 'visible'; b.documentListeners.visibilitychange();
  await tick(); assert.equal(b.requests[0].data.type, 'exposure');
});
test('matching clicks send conversion after exposure; unrelated clicks do not', async () => {
  const b = browser({ path: '/a/', consent: false, initial: {}, goal: 'click' });
  const target = new b.context.Element();
  target.closest = () => null;
  await b.documentListeners.click({ target });
  assert.equal(b.requests.length, 1);
  target.closest = () => target;
  await b.documentListeners.click({ target });
  assert.deepEqual(b.requests.map((request) => request.data.type), ['exposure', 'conversion']);
});
