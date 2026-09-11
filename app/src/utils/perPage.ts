/**
 * perPage.ts
 *
 * Page sizes offered by the paginated admin tables (workflows, message history
 * and processing queue), plus the per-screen memory of the size the viewer
 * picked. The choice lives in localStorage, so it follows the browser rather
 * than the account.
 *
 * @since 2.4.2
 */

/**
 * Page sizes the tables offer, smallest first.
 *
 * @since 2.4.2
 */
export const PER_PAGE_OPTIONS: number[] = [10, 25, 50, 100, 200];

/**
 * Page size used until the viewer picks another one. Mirrors the PHP
 * registries' PER_PAGE, which sizes the bootstrap payload.
 *
 * @since 2.4.2
 */
export const DEFAULT_PER_PAGE = 25;

/**
 * Builds the localStorage key holding a screen's page size.
 *
 * @since 2.4.2
 * @param {string} screen Screen slug (e.g. 'history').
 * @returns {string} The storage key.
 */
function storageKey(screen: string): string {
  return `joinotify-${screen}-per-page`;
}

/**
 * Coerces a value to one of the offered page sizes.
 *
 * @since 2.4.2
 * @param {unknown} value Raw page size.
 * @param {number} [fallback] Size returned when the value is not offered.
 * @returns {number} An offered page size, or the fallback.
 */
export function normalizePerPage(value: unknown, fallback = DEFAULT_PER_PAGE): number {
  const size = Number(value);

  return PER_PAGE_OPTIONS.includes(size) ? size : fallback;
}

/**
 * Reads the page size remembered for a screen.
 *
 * @since 2.4.2
 * @param {string} screen Screen slug.
 * @returns {number|null} The remembered size, or null when none is stored.
 */
export function readStoredPerPage(screen: string): number | null {
  try {
    const stored = window.localStorage.getItem(storageKey(screen));

    return stored === null ? null : normalizePerPage(stored);
  } catch (error) {
    // Storage can be blocked (private mode, site data disabled).
    return null;
  }
}

/**
 * Remembers the page size picked on a screen.
 *
 * @since 2.4.2
 * @param {string} screen Screen slug.
 * @param {number} size The page size.
 */
export function storePerPage(screen: string, size: number): void {
  try {
    window.localStorage.setItem(storageKey(screen), String(size));
  } catch (error) {
    // Not remembering the choice is harmless; the table still resizes.
  }
}

/**
 * Finds the page that keeps the first visible row on screen after the page
 * size changes, so switching sizes does not throw the viewer back to page 1.
 *
 * @since 2.4.2
 * @param {number} currentPage Page shown before the change.
 * @param {number} fromSize Previous page size.
 * @param {number} toSize New page size.
 * @returns {number} The page to show with the new size.
 */
export function pageKeepingFirstRow(currentPage: number, fromSize: number, toSize: number): number {
  const firstRow = (Math.max(1, currentPage) - 1) * Math.max(1, fromSize);

  return Math.floor(firstRow / Math.max(1, toSize)) + 1;
}
