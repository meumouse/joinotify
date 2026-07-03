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
 * Sanitizes preview markup by stripping scripts and inline event handlers,
 * escaping all tags except a safe formatting allowlist, and converting
 * newlines to `<br>`.
 *
 * @since 2.0.0
 * @param {string} value The raw preview markup.
 * @returns {string} The sanitized markup.
 */
export function sanitizePreviewHtml(value: string): string {
  const source = String(value || '');
  const strippedScripts = source.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
  const strippedHandlers = strippedScripts.replace(/\son[a-z]+\s*=\s*(".*?"|'.*?'|[^\s>]+)/gi, '');

  return strippedHandlers
    .replace(/<(?!\/?(strong|em|b|i|u|br|p|ul|ol|li|a)(\s|>|\/))/gi, '&lt;')
    .replace(/\n/g, '<br>');
}
