import { computed, onBeforeUnmount, ref } from 'vue';
import { __, textDomain } from '../utils/i18n';
import { createApiClient } from '../utils/api';

const EMPTY_COUNTS = { all: 0, sent: 0, failed: 0, queued: 0 };

function normalizeCounts(counts) {
  return { ...EMPTY_COUNTS, ...(counts && typeof counts === 'object' ? counts : {}) };
}

function normalizePagination(pagination) {
  const source = pagination && typeof pagination === 'object' ? pagination : {};

  return {
    current_page: Number(source.current_page) || 1,
    per_page: Number(source.per_page) || 20,
    total_items: Number(source.total_items) || 0,
    total_pages: Number(source.total_pages) || 1,
  };
}

/**
 * Server-side message history listing: filtering, pagination, selection and
 * deletion backed by the Joinotify REST endpoints.
 *
 * @since 2.0.0
 * @param {Object} bootstrap Bootstrap payload from the history screen.
 */
export function useMessageHistory(bootstrap = {}) {
  const api = createApiClient(bootstrap);
  const hasApi = Boolean(bootstrap?.rest?.root);

  const loading = ref(false);
  const error = ref('');
  const items = ref(Array.isArray(bootstrap.items) ? bootstrap.items : []);
  const counts = ref(normalizeCounts(bootstrap.counts));
  const pagination = ref(normalizePagination(bootstrap.pagination));
  const sources = ref(Array.isArray(bootstrap.sources) ? bootstrap.sources : []);

  const filters = ref({
    status: '',
    source: '',
    search: '',
    date_from: '',
    date_to: '',
  });

  const selectedIds = ref(new Set());

  const statusTabs = computed(() => [
    { label: __('All', textDomain), value: '', count: counts.value.all },
    { label: __('Sent', textDomain), value: 'sent', count: counts.value.sent },
    { label: __('Failed', textDomain), value: 'failed', count: counts.value.failed },
    { label: __('Queued', textDomain), value: 'queued', count: counts.value.queued },
  ]);

  const totalSelected = computed(() => selectedIds.value.size);

  const allVisibleSelected = computed(
    () => items.value.length > 0 && items.value.every((item) => selectedIds.value.has(String(item.id)))
  );

  const partiallyVisibleSelected = computed(() => {
    if (!items.value.length) {
      return false;
    }

    const selectedVisibleCount = items.value.filter((item) => selectedIds.value.has(String(item.id))).length;

    return selectedVisibleCount > 0 && selectedVisibleCount < items.value.length;
  });

  const pageSummary = computed(() => {
    const total = pagination.value.total_items;

    if (!total) {
      return __('0 results', textDomain);
    }

    const start = (pagination.value.current_page - 1) * pagination.value.per_page + 1;
    const end = Math.min(pagination.value.current_page * pagination.value.per_page, total);

    return `${start}-${end} ${__('of', textDomain)} ${total}`;
  });

  function buildQuery() {
    const query = new URLSearchParams();
    query.set('page', String(pagination.value.current_page));
    query.set('per_page', String(pagination.value.per_page));

    Object.entries(filters.value).forEach(([key, value]) => {
      if (value) {
        query.set(key, String(value));
      }
    });

    return query.toString();
  }

  async function fetchItems() {
    if (!hasApi) {
      return;
    }

    loading.value = true;
    error.value = '';

    try {
      const response = await api.get(`/admin/history?${buildQuery()}`);

      if (response?.status === 'error') {
        throw new Error(response.message || __('Could not load the message history.', textDomain));
      }

      items.value = Array.isArray(response?.items) ? response.items : [];
      counts.value = normalizeCounts(response?.counts);
      pagination.value = normalizePagination(response?.pagination);
      selectedIds.value = new Set();
    } catch (fetchError) {
      error.value = fetchError instanceof Error ? fetchError.message : __('Could not load the message history.', textDomain);
    } finally {
      loading.value = false;
    }
  }

  let searchTimer = null;

  function applyFilters() {
    pagination.value = { ...pagination.value, current_page: 1 };
    fetchItems();
  }

  function setStatusFilter(status) {
    filters.value = { ...filters.value, status: status || '' };
    applyFilters();
  }

  function setSourceFilter(source) {
    filters.value = { ...filters.value, source: source || '' };
    applyFilters();
  }

  function setDateRange(from, to) {
    filters.value = { ...filters.value, date_from: from || '', date_to: to || '' };
    applyFilters();
  }

  function setSearch(value) {
    filters.value = { ...filters.value, search: value || '' };

    if (searchTimer) {
      window.clearTimeout(searchTimer);
    }

    searchTimer = window.setTimeout(applyFilters, 350);
  }

  function goToPage(page) {
    const target = Math.min(Math.max(1, page), pagination.value.total_pages);

    if (target === pagination.value.current_page) {
      return;
    }

    pagination.value = { ...pagination.value, current_page: target };
    fetchItems();
  }

  const firstPage = () => goToPage(1);
  const previousPage = () => goToPage(pagination.value.current_page - 1);
  const nextPage = () => goToPage(pagination.value.current_page + 1);
  const lastPage = () => goToPage(pagination.value.total_pages);

  function toggleSelected(id, checked) {
    const next = new Set(selectedIds.value);
    const key = String(id);

    if (checked) {
      next.add(key);
    } else {
      next.delete(key);
    }

    selectedIds.value = next;
  }

  function toggleSelectAll(checked) {
    if (!checked) {
      selectedIds.value = new Set();
      return;
    }

    selectedIds.value = new Set(items.value.map((item) => String(item.id)));
  }

  async function removeSelected() {
    if (!hasApi || !selectedIds.value.size) {
      return;
    }

    loading.value = true;
    error.value = '';

    try {
      const response = await api.post('/admin/history/delete', { ids: Array.from(selectedIds.value) });

      if (response?.status === 'error') {
        throw new Error(response.message || __('Could not delete the selected records.', textDomain));
      }

      items.value = Array.isArray(response?.items) ? response.items : [];
      counts.value = normalizeCounts(response?.counts);
      pagination.value = normalizePagination(response?.pagination);
      selectedIds.value = new Set();
    } catch (deleteError) {
      error.value = deleteError instanceof Error ? deleteError.message : __('Could not delete the selected records.', textDomain);
    } finally {
      loading.value = false;
    }
  }

  async function clearAll() {
    if (!hasApi) {
      return;
    }

    loading.value = true;
    error.value = '';

    try {
      const response = await api.post('/admin/history/delete', { all: true });

      if (response?.status === 'error') {
        throw new Error(response.message || __('Could not clear the history.', textDomain));
      }

      items.value = Array.isArray(response?.items) ? response.items : [];
      counts.value = normalizeCounts(response?.counts);
      pagination.value = normalizePagination(response?.pagination);
      selectedIds.value = new Set();
    } catch (clearError) {
      error.value = clearError instanceof Error ? clearError.message : __('Could not clear the history.', textDomain);
    } finally {
      loading.value = false;
    }
  }

  onBeforeUnmount(() => {
    if (searchTimer) {
      window.clearTimeout(searchTimer);
    }
  });

  return {
    loading,
    error,
    items,
    counts,
    pagination,
    sources,
    filters,
    selectedIds,
    statusTabs,
    totalSelected,
    allVisibleSelected,
    partiallyVisibleSelected,
    pageSummary,
    fetchItems,
    reload: fetchItems,
    setStatusFilter,
    setSourceFilter,
    setDateRange,
    setSearch,
    firstPage,
    previousPage,
    nextPage,
    lastPage,
    goToPage,
    toggleSelected,
    toggleSelectAll,
    removeSelected,
    clearAll,
  };
}
