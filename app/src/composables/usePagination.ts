import { computed, ref, unref, watch } from 'vue';

function resolveSource(source) {
  if (typeof source === 'function') {
    return source() || [];
  }

  return unref(source) || [];
}

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

  function clampPage(page) {
    const nextPage = Number(page) || 1;
    currentPage.value = Math.min(Math.max(nextPage, 1), totalPages.value);
  }

  function firstPage() {
    clampPage(1);
  }

  function previousPage() {
    clampPage(currentPage.value - 1);
  }

  function nextPage() {
    clampPage(currentPage.value + 1);
  }

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
