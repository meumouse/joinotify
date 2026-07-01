/**
 * i18n.ts
 *
 * Thin wrapper around WordPress's `wp.i18n` runtime. Provides translation and
 * sprintf helpers that fall back to the untranslated text when the WordPress
 * i18n runtime is unavailable.
 *
 * @since 2.0.0
 */
const textDomain = 'joinotify';

/**
 * Returns the WordPress i18n runtime if present.
 *
 * @since 2.0.0
 * @returns {Object|null} The `wp.i18n` object, or null when unavailable.
 */
function getWpI18n() {
  return globalThis?.wp?.i18n || null;
}

/**
 * Translates a string using the WordPress i18n runtime.
 *
 * @since 2.0.0
 * @param {string} text The text to translate.
 * @param {string} [domain] The text domain.
 * @returns {string} The translated string, or the original when unavailable.
 */
export function __(text: string, domain = textDomain): string {
  const translator = getWpI18n()?.__;

  if (typeof translator === 'function') {
    return translator(text, domain);
  }

  return text;
}

/**
 * Formats a string using the WordPress i18n sprintf implementation.
 *
 * @since 2.0.0
 * @param {string} message The format string.
 * @param {...unknown} args The substitution arguments.
 * @returns {string} The formatted string, or the original when unavailable.
 */
export function sprintf(message: string, ...args: unknown[]): string {
  const formatter = getWpI18n()?.sprintf;

  if (typeof formatter === 'function') {
    return formatter(message, ...args);
  }

  return message;
}

export { textDomain };
