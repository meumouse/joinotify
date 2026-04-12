const textDomain = 'joinotify';

/**
 * Return the WordPress i18n helper bundle when available.
 *
 * @since 1.4.7
 * @version 1.4.7
 * @return {Object|null} WordPress i18n helpers or null when unavailable.
 */
function getWpI18n() {
  return globalThis?.wp?.i18n || null;
}

/**
 * Translate a string using the Joinotify text domain.
 *
 * @since 1.4.7
 * @version 1.4.7
 * @param {string} text - Source string to translate.
 * @param {string} [domain=textDomain] - Translation domain.
 * @return {string} Translated text or the original string when translations are unavailable.
 */
export function __(text, domain = textDomain) {
  const translator = getWpI18n()?.__;

  if (typeof translator === 'function') {
    return translator(text, domain);
  }

  return text;
}

/**
 * Format a string with WordPress sprintf when available.
 *
 * @since 1.4.7
 * @version 1.4.7
 * @param {string} message - Format string.
 * @param {...mixed} args - Values to interpolate into the format string.
 * @return {string} Formatted message or the original string when sprintf is unavailable.
 */
export function sprintf(message, ...args) {
  const formatter = getWpI18n()?.sprintf;

  if (typeof formatter === 'function') {
    return formatter(message, ...args);
  }

  return message;
}

export { textDomain };
