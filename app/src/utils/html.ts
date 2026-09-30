/**
 * html.ts
 *
 * HTML utility helpers for escaping user-provided text and sanitizing preview
 * markup. Used to safely render message previews while allowing a small set of
 * formatting tags.
 *
 * @since 2.0.0
 */

/**
 * Escapes HTML-special characters in a string.
 *
 * @since 2.0.0
 * @param {string} value The raw string.
 * @returns {string} The escaped string.
 */
export function escapeHtml(value: string): string {
  return String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

/**
 * Formatting tags a preview may keep. Everything else is shown as the text it was typed as.
 */
const ALLOWED_TAGS = new Set(['strong', 'em', 'b', 'i', 'u', 's', 'code', 'br', 'p', 'ul', 'ol', 'li', 'a', 'span', 'div']);

/** Tags that never have content, so they are emitted without a closing pair. */
const VOID_TAGS = new Set(['br']);

/**
 * The inline styles the plugin itself writes for WhatsApp formatting (`Builder\Messages`), and
 * that stored workflows still carry. Any other declaration is dropped.
 */
const ALLOWED_STYLES = new Set([
  'font-weight: bold',
  'font-style: italic',
  'text-decoration: line-through',
  'font-family: monospace',
]);

/** A tag, opening or closing. The name must follow `<` directly, as it must for a browser. */
const TAG = /<(\/?)([a-zA-Z][a-zA-Z0-9]*)([^<>]*)>/g;

/**
 * Reads one attribute's value from a tag's attribute text.
 *
 * @since 2.5.0
 * @param {string} attributes The text between the tag name and `>`.
 * @param {string} name The attribute to read.
 * @returns {string|null} The raw value, or null when absent.
 */
function readAttribute(attributes: string, name: string): string | null {
  const match = new RegExp(`(?:^|[\\s/])${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s"'=<>\`]+))`, 'i').exec(attributes);

  return match ? (match[1] ?? match[2] ?? match[3] ?? '') : null;
}

/**
 * Returns the href when it is an absolute http(s) or mailto URL, otherwise null.
 *
 * An allowlist, not a denylist: `javascript:`, `data:`, entity-encoded schemes and anything the
 * URL parser would read differently all fail the same test.
 *
 * @since 2.5.0
 * @param {string|null} href The raw attribute value.
 * @returns {string|null} The safe href.
 */
function safeHref(href: string | null): string | null {
  const value = String(href ?? '').trim();

  return /^(https?:\/\/|mailto:)[^\s\u0000-\u001f]*$/i.test(value) ? value : null;
}

/**
 * Rebuilds an allowlisted tag from scratch, keeping only vetted attributes.
 *
 * Nothing of the original tag is copied through: event handlers, `style` payloads and unknown
 * attributes simply have nowhere to go.
 *
 * @since 2.5.0
 * @param {string} name Lowercased tag name.
 * @param {string} attributes The original attribute text.
 * @returns {string} The clean opening tag.
 */
function openingTag(name: string, attributes: string): string {
  const kept: string[] = [];

  if (name === 'a') {
    const href = safeHref(readAttribute(attributes, 'href'));

    if (href) {
      kept.push(`href="${escapeHtml(href)}"`, 'target="_blank"', 'rel="noopener noreferrer"');
    }
  }

  if (name === 'span' || name === 'div') {
    const className = readAttribute(attributes, 'class');

    if (className && /^[\w\s-]+$/.test(className)) {
      kept.push(`class="${className.trim()}"`);
    }

    const style = (readAttribute(attributes, 'style') || '')
      .split(';')
      .map((declaration) => declaration.trim().replace(/\s*:\s*/, ': ').toLowerCase())
      .filter((declaration) => ALLOWED_STYLES.has(declaration));

    if (style.length) {
      kept.push(`style="${style.join('; ')};"`);
    }
  }

  return kept.length ? `<${name} ${kept.join(' ')}>` : `<${name}>`;
}

/**
 * Sanitizes markup to a small formatting allowlist.
 *
 * Every `<` in the output belongs to a tag this function wrote itself: allowlisted tags are
 * rebuilt with vetted attributes only, and every other `<` or `>` is escaped, so a tag outside
 * the list (or a `<script>`, or an `<img onerror>`) reads as the characters that were typed.
 * Entities are left alone — `&lt;` can only ever become text when the result is parsed.
 *
 * @since 2.5.0
 * @param {string} value The raw markup.
 * @returns {string} The sanitized markup.
 */
export function sanitizeHtml(value: string): string {
  const source = String(value || '');
  const escapeText = (text: string) => text.replaceAll('<', '&lt;').replaceAll('>', '&gt;');
  let out = '';
  let last = 0;

  for (const match of source.matchAll(TAG)) {
    const [raw, closing, rawName, attributes] = match;
    const name = rawName.toLowerCase();
    const index = match.index ?? 0;

    out += escapeText(source.slice(last, index));
    last = index + raw.length;

    if (!ALLOWED_TAGS.has(name)) {
      out += escapeText(raw);
    } else if (closing) {
      out += VOID_TAGS.has(name) ? '' : `</${name}>`;
    } else {
      out += VOID_TAGS.has(name) ? `<${name}>` : openingTag(name, attributes);
    }
  }

  return out + escapeText(source.slice(last));
}

/**
 * Sanitizes preview markup (see `sanitizeHtml`) and converts newlines to `<br>`.
 *
 * @since 2.0.0
 * @version 2.5.0
 * @param {string} value The raw preview markup.
 * @returns {string} The sanitized markup.
 */
export function sanitizePreviewHtml(value: string): string {
  return sanitizeHtml(value).replace(/\n/g, '<br>');
}
