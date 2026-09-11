import { computed, onBeforeUnmount, ref } from 'vue';
import { __, sprintf, textDomain } from '../utils/i18n';
import { createApiClient } from '../utils/api';
import { downloadJson } from '../utils/downloadJson';
import { DEFAULT_PER_PAGE, normalizePerPage, pageKeepingFirstRow, readStoredPerPage, storePerPage } from '../utils/perPage';

const EMPTY_COUNTS = { all: 0, sent: 0, failed: 0, queued: 0, cancelled: 0 };

function normalizeCounts(counts) {
  return { ...EMPTY_COUNTS, ...(counts && typeof counts === 'object' ? counts : {}) };
}

function normalizePagination(pagination) {
  const source = pagination && typeof pagination === 'object' ? pagination : {};

  return {
    current_page: Number(source.current_page) || 1,
    per_page: Number(source.per_page) || DEFAULT_PER_PAGE,
    total_items: Number(source.total_items) || 0,
    total_pages: Number(source.total_pages) || 1,
  };
}

/**
 * Server-side message history listing: filtering, pagination, selection,
 * deletion and JSON export backed by the Joinotify REST endpoints.
 *
 * @since 2.0.0
 * @version 2.4.2
 * @param {Object} bootstrap Bootstrap payload from the history screen.
 */
export function useMessageHistory(bootstrap = {}) {
  const api = createApiClient(bootstrap);
  const hasApi = Boolean(bootstrap?.rest?.root);

  const loading = ref(false);
  const exporting = ref(false);
  const error = ref('');
  const notice = ref('');
  const items = ref(Array.isArray(bootstrap.items) ? bootstrap.items : []);
  const counts = ref(normalizeCounts(bootstrap.counts));
  const pagination = ref(normalizePagination(bootstrap.pagination));
  const sources = ref(Array.isArray(bootstrap.sources) ? bootstrap.sources : []);
  const storedPerPage = readStoredPerPage('history');

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
    { label: __('Cancelled', textDomain), value: 'cancelled', count: counts.value.cancelled },
  ]);

  const totalSelected = computed(() => selectedIds.value.size);

  // Selection never spans pages (a reload clears it), so the visible rows are
  // the whole selection and can answer whether a resend is left to cancel.
  const cancellableSelected = computed(
    () => items.value.filter((item) => item?.can_cancel_retry && selectedIds.value.has(String(item.id))).length
  );

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

  // The write endpoints answer with a refreshed first page. Sending the active
  // filters and page size makes it the list on screen rather than the default.
  function listArgs() {
    return { ...filters.value, per_page: pagination.value.per_page };
  }

  async function fetchItems() {
    if (!hasApi) {
      return;
    }

    loading.value = true;
    error.value = '';
    notice.value = '';

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

  /**
   * Change how many records a page shows, keeping the first visible row on
   * screen, and remember the choice for the next visit.
   *
   * @since 2.4.2
   * @param {number} size The new page size.
   */
  function setPerPage(size) {
    const current = pagination.value;
    const nextSize = normalizePerPage(size, current.per_page);

    if (nextSize === current.per_page) {
      return;
    }

    storePerPage('history', nextSize);

    pagination.value = {
      ...current,
      per_page: nextSize,
      current_page: pageKeepingFirstRow(current.current_page, current.per_page, nextSize),
    };

    fetchItems();
  }

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
      const response = await api.post('/admin/history/delete', { ...listArgs(), ids: Array.from(selectedIds.value) });

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

  /**
   * Call off the pending resend of the selected rows.
   *
   * The queue items behind them are discarded and the records settle as
   * cancelled; rows whose retry already ran are left alone and reported back.
   *
   * @since 2.4.0
   */
  async function cancelRetrySelected() {
    if (!hasApi || !selectedIds.value.size) {
      return;
    }

    loading.value = true;
    error.value = '';
    notice.value = '';

    try {
      const response = await api.post('/admin/history/cancel-retry', { ...listArgs(), ids: Array.from(selectedIds.value) });

      if (response?.status === 'error') {
        throw new Error(response.message || __('Could not cancel the resend.', textDomain));
      }

      items.value = Array.isArray(response?.items) ? response.items : [];
      counts.value = normalizeCounts(response?.counts);
      pagination.value = normalizePagination(response?.pagination);
      selectedIds.value = new Set();
      notice.value = response?.message || '';
    } catch (cancelError) {
      error.value = cancelError instanceof Error ? cancelError.message : __('Could not cancel the resend.', textDomain);
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
      const response = await api.post('/admin/history/delete', { ...listArgs(), all: true });

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

  /**
   * Download history records as a JSON file.
   *
   * @since 2.4.2
   * @param {Object} body Either `{ ids }` or `{ all: true, ...filters }`.
   * @returns {Promise<void>} Resolves once the download has started or failed.
   */
  async function requestExport(body) {
    if (!hasApi || exporting.value) {
      return;
    }

    exporting.value = true;
    error.value = '';
    notice.value = '';

    try {
      const response = await api.post('/admin/history/export', body);

      if (response?.status === 'error' || !response?.payload) {
        throw new Error(response?.message || __('Could not export the message history.', textDomain));
      }

      downloadJson(response.payload, response.filename);

      if (response.payload.truncated) {
        notice.value = sprintf(
          /* translators: 1: number of exported records, 2: number of records matching the filters */
          __('The export holds only the %1$d most recent of the %2$d matching records.', textDomain),
          response.payload.items?.length || 0,
          response.payload.total || 0
        );
      }
    } catch (exportError) {
      error.value = exportError instanceof Error ? exportError.message : __('Could not export the message history.', textDomain);
    } finally {
      exporting.value = false;
    }
  }

  /**
   * Export the selected records, or every record matching the active filters
   * when nothing is selected.
   *
   * @since 2.4.2
   * @returns {Promise<void>}
   */
  function exportRecords() {
    if (selectedIds.value.size) {
      return requestExport({ ids: Array.from(selectedIds.value) });
    }

    return requestExport({ ...filters.value, all: true });
  }

  /**
   * Export a single record.
   *
   * @since 2.4.2
   * @param {number|string} id History record ID.
   * @returns {Promise<void>}
   */
  function exportRecord(id) {
    return requestExport({ ids: [String(id)] });
  }

  onBeforeUnmount(() => {
    if (searchTimer) {
      window.clearTimeout(searchTimer);
    }
  });

  // The bootstrap payload is sized with the server default. When the viewer
  // picked another size, reload before the first render shows the wrong one.
  if (hasApi && storedPerPage && storedPerPage !== pagination.value.per_page) {
    pagination.value = { ...pagination.value, per_page: storedPerPage, current_page: 1 };
    fetchItems();
  }

  return {
    loading,
    exporting,
    error,
    notice,
    items,
    counts,
    pagination,
    sources,
    filters,
    selectedIds,
    statusTabs,
    totalSelected,
    cancellableSelected,
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
    setPerPage,
    toggleSelected,
    toggleSelectAll,
    removeSelected,
    cancelRetrySelected,
    clearAll,
    exportRecords,
    exportRecord,
  };
}
