/**
 * icon.ts
 *
 * Helpers for detecting and resolving inline SVG icon markup, with support for
 * falling back to a secondary source when the primary is not valid SVG.
 *
 * @since 2.0.0
 */

/**
 * Determines whether a value is inline SVG markup.
 *
 * @since 2.0.0
 * @param {unknown} value The value to test.
 * @returns {boolean} True when the value is a string beginning with `<svg`.
 */
export function isSvgMarkup(value: unknown): value is string {
  return typeof value === 'string' && value.trim().startsWith('<svg');
}

/**
 * Resolves the first valid SVG markup from a primary/fallback pair.
 *
 * @since 2.0.0
 * @param {unknown} [primary] The preferred SVG source.
 * @param {unknown} [fallback] The fallback SVG source.
 * @returns {string} The trimmed SVG markup, or an empty string when none valid.
 */
export function resolveSvgMarkup(primary?: unknown, fallback?: unknown): string {
  if (isSvgMarkup(primary)) {
    return primary.trim();
  }

  if (isSvgMarkup(fallback)) {
    return fallback.trim();
  }

  return '';
}
