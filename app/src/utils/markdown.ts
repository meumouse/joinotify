/**
 * markdown.ts
 *
 * Tiny, dependency-free markdown renderer used by canvas sticky notes. It is
 * intentionally minimal and safe: the raw source is HTML-escaped first, then a
 * small, well-known subset of markdown is converted to markup. Because escaping
 * happens before any conversion, the output never contains user-authored HTML,
 * so it is safe to inject with v-html.
 *
 * Supported: headings (#..###), bold, italic, inline code, fenced/indented code
 * blocks, links [text](url) restricted to http(s)/mailto, unordered and ordered
 * lists, horizontal rules, and paragraph/line breaks.
 *
 * @since 2.0.0
 */

/**
 * Escapes HTML-significant characters so the source can never inject markup.
 *
 * @since 2.0.0
 * @param {string} value The raw text.
 * @returns {string} The escaped text.
 */
function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Returns a safe href for a link, or an empty string when the protocol is not
 * allowlisted. Operates on already-escaped text.
 *
 * @since 2.0.0
 * @param {string} escapedUrl The escaped URL candidate.
 * @returns {string} The safe href, or an empty string.
 */
function safeHref(escapedUrl: string): string {
  const url = escapedUrl.trim();

  if (/^(https?:\/\/|mailto:)/i.test(url)) {
    return url;
  }

  // Allow bare domains / relative-looking links by defaulting to https.
  if (/^[\w.-]+\.[a-z]{2,}(\/\S*)?$/i.test(url)) {
    return `https://${url}`;
  }

  return '';
}

/**
 * Applies inline markdown (code, bold, italic, links) to an escaped line.
 *
 * @since 2.0.0
 * @param {string} text The escaped line text.
 * @returns {string} The line with inline markup applied.
 */
function renderInline(text: string): string {
  let out = text;

  // Inline code first so its content is not touched by other rules.
  out = out.replace(/`([^`]+)`/g, (_match, code) => `<code>${code}</code>`);

  // Links: [label](url) — url already escaped; drop link if protocol unsafe.
  out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_match, label, url) => {
    const href = safeHref(String(url));
    return href
      ? `<a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`
      : label;
  });

  // Bold then italic (order matters for ** vs *).
  out = out.replace(/\*\*([^*]+)\*\*/g, (_match, inner) => `<strong>${inner}</strong>`);
  out = out.replace(/__([^_]+)__/g, (_match, inner) => `<strong>${inner}</strong>`);
  out = out.replace(/\*([^*]+)\*/g, (_match, inner) => `<em>${inner}</em>`);
  out = out.replace(/_([^_]+)_/g, (_match, inner) => `<em>${inner}</em>`);

  return out;
}

/**
 * Renders a limited, safe subset of markdown to an HTML string.
 *
 * @since 2.0.0
 * @param {string} source The markdown source text.
 * @returns {string} The rendered HTML.
 */
export function renderMarkdown(source: string): string {
  const escaped = escapeHtml(String(source ?? '').replace(/\r\n/g, '\n'));
  const lines = escaped.split('\n');
  const html: string[] = [];

  let listType: 'ul' | 'ol' | null = null;
  let inCodeBlock = false;
  const codeBuffer: string[] = [];
  let paragraph: string[] = [];

  const closeList = () => {
    if (listType) {
      html.push(`</${listType}>`);
      listType = null;
    }
  };

  const flushParagraph = () => {
    if (paragraph.length > 0) {
      html.push(`<p>${paragraph.map(renderInline).join('<br>')}</p>`);
      paragraph = [];
    }
  };

  const flushCode = () => {
    html.push(`<pre><code>${codeBuffer.join('\n')}</code></pre>`);
    codeBuffer.length = 0;
  };

  for (const line of lines) {
    // Fenced code blocks (```): toggle and buffer verbatim.
    if (/^\s*```/.test(line)) {
      if (inCodeBlock) {
        flushCode();
        inCodeBlock = false;
      } else {
        flushParagraph();
        closeList();
        inCodeBlock = true;
      }
      continue;
    }

    if (inCodeBlock) {
      codeBuffer.push(line);
      continue;
    }

    // Blank line: paragraph / list separator.
    if (/^\s*$/.test(line)) {
      flushParagraph();
      closeList();
      continue;
    }

    // Headings.
    const heading = line.match(/^(#{1,3})\s+(.*)$/);
    if (heading) {
      flushParagraph();
      closeList();
      const level = heading[1].length;
      html.push(`<h${level}>${renderInline(heading[2])}</h${level}>`);
      continue;
    }

    // Horizontal rule.
    if (/^\s*(-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
      flushParagraph();
      closeList();
      html.push('<hr>');
      continue;
    }

    // Unordered list item.
    const unordered = line.match(/^\s*[-*+]\s+(.*)$/);
    if (unordered) {
      flushParagraph();
      if (listType !== 'ul') {
        closeList();
        html.push('<ul>');
        listType = 'ul';
      }
      html.push(`<li>${renderInline(unordered[1])}</li>`);
      continue;
    }

    // Ordered list item.
    const ordered = line.match(/^\s*\d+\.\s+(.*)$/);
    if (ordered) {
      flushParagraph();
      if (listType !== 'ol') {
        closeList();
        html.push('<ol>');
        listType = 'ol';
      }
      html.push(`<li>${renderInline(ordered[1])}</li>`);
      continue;
    }

    // Otherwise accumulate into the current paragraph.
    closeList();
    paragraph.push(line.trim());
  }

  if (inCodeBlock) {
    flushCode();
  }

  flushParagraph();
  closeList();

  return html.join('\n');
}
