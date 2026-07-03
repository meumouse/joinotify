/**
 * usePagination.ts
 *
 * Client-side pagination composable. Slices a (possibly reactive) list into
 * pages and exposes navigation helpers plus derived state such as total pages
 * and boundary flags. Automatically clamps the current page when the source
 * changes.
 *
 * @since 2.0.0
 */
import { computed, ref, unref, watch } from 'vue';

/**
 * Resolves the pagination source, supporting a getter function or ref/value.
 *
 * @since 2.0.0
 * @param {Function|Ref<Array>|Array} source Items source.
 * @returns {Array} The resolved array of items.
 */
function resolveSource(source) {
  if (typeof source === 'function') {
    return source() || [];
  }

  return unref(source) || [];
}

/**
 * Provides client-side pagination over a list of items.
 *
 * @since 2.0.0
 * @param {Function|Ref<Array>|Array} source The items source to paginate.
 * @param {Object} [options] Pagination options (currentPage, perPage).
 * @returns {Object} Pagination state and navigation helpers.
 */
export function usePagination(source, options = {}) {
  const currentPage = ref(Number(options.currentPage) || 1);
  const perPage = ref(Number(options.perPage) || 20);

  const items = computed(() => resolveSource(source));
  const totalItems = computed(() => items.value.length);
  const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / perPage.value)));

  const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return items.value.slice(start, start + perPage.value);
  });

  const canGoPrevious = computed(() => currentPage.value > 1);
  const canGoNext = computed(() => currentPage.value < totalPages.value);

  /**
   * Sets the current page, clamped to the valid range.
   *
   * @since 2.0.0
   * @param {number} page The desired page number.
   */
  function clampPage(page) {
    const nextPage = Number(page) || 1;
    currentPage.value = Math.min(Math.max(nextPage, 1), totalPages.value);
  }

  /**
   * Navigates to the first page.
   *
   * @since 2.0.0
   */
  function firstPage() {
    clampPage(1);
  }

  /**
   * Navigates to the previous page.
   *
   * @since 2.0.0
   */
  function previousPage() {
    clampPage(currentPage.value - 1);
  }

  /**
   * Navigates to the next page.
   *
   * @since 2.0.0
   */
  function nextPage() {
    clampPage(currentPage.value + 1);
  }

  /**
   * Navigates to the last page.
   *
   * @since 2.0.0
   */
  function lastPage() {
    clampPage(totalPages.value);
  }

  watch(
    [totalPages, totalItems],
    () => {
      if (currentPage.value > totalPages.value) {
        currentPage.value = totalPages.value;
      }

      if (currentPage.value < 1) {
        currentPage.value = 1;
      }
    },
    { immediate: true }
  );

  return {
    canGoNext,
    canGoPrevious,
    clampPage,
    currentPage,
    firstPage,
    items,
    lastPage,
    nextPage,
    paginatedItems,
    perPage,
    previousPage,
    setPage: clampPage,
    totalItems,
    totalPages,
  };
}
