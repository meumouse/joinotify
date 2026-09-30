/**
 * sanitize.browser.mjs
 *
 * Browser regression test for the canvas's HTML sanitizer (`src/utils/html.ts`).
 *
 * Node descriptions reach `v-html` from stored workflows, imported files, remote templates and
 * the AI generator, so what matters is what a real browser builds from the sanitizer's output,
 * not what a regex thinks of it. Each payload is mounted with Vue's own `v-html` in headless
 * Chromium, on a blank page with no network; every element then gets the events a person could
 * fire, and the test fails if a marker element exists, an `on*` attribute survives, a link
 * leaves http(s)/mailto, or `window.xssFired` is ever set.
 *
 * Run: `npm run test:browser` (first time: `npx playwright install chromium`).
 * `SANITIZER_FILE=<path>` points it at another copy of html.ts, and `SANITIZER_FNS` picks the
 * exports to test — used to prove the test fails against the regex sanitizer this replaced
 * (`SANITIZER_FNS=sanitizePreviewHtml`, the only export it had).
 *
 * @since 2.5.0
 */
import { test, after, before } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { chromium } from 'playwright';
import { transformWithEsbuild } from 'vite';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sanitizerFile = resolve(root, process.env.SANITIZER_FILE || 'src/utils/html.ts');
const FNS = (process.env.SANITIZER_FNS || 'sanitizeHtml,sanitizePreviewHtml').split(',');

// The harmless marker: an image that counts on `window.xssFired` if its handler runs, and a
// `<button>` tagged so it can be found. Neither is on the formatting allowlist, so the whole
// marker must come out as the characters that were typed.
const MARK = '<img src=x onerror="window.xssFired=(window.xssFired||0)+1"><button data-xss-marker>xss</button>';

const HOSTILE = {
  marker: MARK,
  'marker between formatting': `<strong>Olá</strong> ${MARK} <span class="builder-placeholder">{{ first_name }}</span>`,
  // `<b>` IS allowlisted: it stays bold, but is rebuilt without the attribute.
  'allowed tag carrying a foreign attribute': '<b data-xss-marker title="t">b</b>',
  'slash instead of space before the handler': '<a/onmouseover="window.xssFired=1">hover</a><b/onclick=window.xssFired=1>b</b>',
  'handler on an allowed tag': '<p onclick="window.xssFired=1" class="x">p</p><div class="builder-placeholder" onmouseover=window.xssFired=1>d</div>',
  'javascript href': '<a href="javascript:window.xssFired=1">x</a><a href=" JaVaScRiPt:window.xssFired=1">y</a>',
  'entity-encoded scheme': '<a href="jav&#x61;script:window.xssFired=1">x</a><a href="&#106;avascript:window.xssFired=1">y</a>',
  'data and vbscript hrefs': '<a href="data:text/html,<script>window.xssFired=1</script>">x</a><a href="vbscript:x">y</a>',
  'handler after a legitimate href': '<a href="https://example.com" onclick="window.xssFired=1">x</a>',
  'quote breakout in href': '<a href="https://example.com/"onmouseover="window.xssFired=1">x</a>',
  'style payload': '<span style="background:url(javascript:window.xssFired=1);font-weight: bold">s</span>',
  'svg and iframe': '<svg onload="window.xssFired=1"></svg><iframe srcdoc="<script>parent.xssFired=1</script>"></iframe>',
  'script block': '<script>window.xssFired=1</script>',
  'unterminated tag': '<img src=x onerror=window.xssFired=1 ',
  'tag split by a newline': '<img\nsrc=x\nonerror=window.xssFired=1>',
  'uppercase tag': '<IMG SRC=x ONERROR=window.xssFired=1>',
};

let browser;
let page;

before(async () => {
  const source = await readFile(sanitizerFile, 'utf8');
  const { code } = await transformWithEsbuild(source, sanitizerFile, { loader: 'ts', format: 'esm' });
  const vue = await readFile(resolve(root, 'node_modules/vue/dist/vue.esm-browser.prod.js'), 'utf8');

  browser = await chromium.launch();
  const context = await browser.newContext();
  // Isolated: nothing leaves the page. `<img src=x>` would otherwise ask for /x.
  await context.route('**/*', (route) => route.abort());
  page = await context.newPage();
  await page.setContent('<!doctype html><meta charset="utf-8"><div id="app"></div>');

  // Modules loaded from blob URLs: the page has no origin to serve them from.
  await page.evaluate(
    async ({ vue, code }) => {
      const url = (text) => URL.createObjectURL(new Blob([text], { type: 'text/javascript' }));
      window.Vue = await import(url(vue));
      window.sanitizer = await import(url(code));
    },
    { vue, code },
  );
});

after(async () => {
  await browser?.close();
});

/**
 * Mounts `html` through `v-html` after the named sanitizer, pokes every element, and reports
 * what the DOM ended up holding.
 */
async function render(html, fn = FNS[0]) {
  return page.evaluate(
    async ({ html, fn }) => {
      window.xssFired = undefined;
      const host = document.getElementById('app');
      host.innerHTML = '<div id="mount"></div>';

      const app = window.Vue.createApp({
        data: () => ({ html: window.sanitizer[fn](html) }),
        template: '<div id="out" v-html="html"></div>',
      });
      app.mount('#mount');
      await window.Vue.nextTick();

      const out = document.getElementById('out');
      for (const el of out.querySelectorAll('*')) {
        for (const type of ['click', 'mouseover', 'mouseenter', 'focus', 'load', 'error']) {
          el.dispatchEvent(new Event(type, { bubbles: true }));
        }
      }
      // An <img onerror> fires on its own once the (aborted) request fails.
      await new Promise((done) => setTimeout(done, 150));

      const elements = [...out.querySelectorAll('*')];
      const report = {
        html: out.innerHTML,
        text: out.textContent,
        tags: elements.map((el) => el.tagName.toLowerCase()),
        markers: out.querySelectorAll('[data-xss-marker]').length,
        handlers: elements.flatMap((el) => [...el.attributes].map((a) => a.name).filter((n) => n.startsWith('on'))),
        hrefs: elements.filter((el) => el.hasAttribute('href')).map((el) => el.getAttribute('href')),
        fired: window.xssFired,
      };
      app.unmount();
      return report;
    },
    { html, fn },
  );
}

const DANGEROUS = new Set(['img', 'svg', 'iframe', 'script', 'object', 'embed', 'style', 'math', 'form', 'input']);

for (const [name, payload] of Object.entries(HOSTILE)) {
  test(`hostile description renders inert: ${name}`, async () => {
    for (const fn of FNS) {
      const result = await render(payload, fn);
      const where = `${fn} → ${result.html}`;

      assert.equal(result.fired, undefined, `a handler ran (${where})`);
      assert.equal(result.markers, 0, `the marker became an element (${where})`);
      assert.deepEqual(result.handlers, [], `an on* attribute survived (${where})`);
      assert.deepEqual(result.tags.filter((tag) => DANGEROUS.has(tag)), [], `a dangerous element was built (${where})`);
      for (const href of result.hrefs) {
        assert.match(href, /^(https?:\/\/|mailto:)/i, `unsafe href ${href} (${where})`);
      }
    }
  });
}

test('the marker shows up as the text that was typed', async () => {
  const result = await render(MARK);
  assert.equal(result.text, MARK);
  assert.deepEqual(result.tags, []);
});

test('the formatting the canvas draws survives', async () => {
  const result = await render(
    'Olá, <span class="builder-placeholder">{{ first_name }}</span>! <strong>Pedido</strong> <em>pago</em> ' +
      '<span style="font-weight: bold;">negrito</span> <a href="https://example.com/a?b=1">link</a><br>fim',
  );
  assert.deepEqual(result.tags, ['span', 'strong', 'em', 'span', 'a', 'br']);
  assert.match(result.html, /<span class="builder-placeholder">\{\{ first_name \}\}<\/span>/);
  assert.match(result.html, /<span style="font-weight: bold;">negrito<\/span>/);
  assert.match(result.html, /<a href="https:\/\/example\.com\/a\?b=1" target="_blank" rel="noopener noreferrer">link<\/a>/);
});

test('entities stay text', async () => {
  const result = await render('&lt;img src=x onerror=window.xssFired=1&gt; A &amp; B');
  assert.equal(result.text, '<img src=x onerror=window.xssFired=1> A & B');
  assert.deepEqual(result.tags, []);
});
