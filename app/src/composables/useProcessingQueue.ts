import { computed, ref } from 'vue';
import { __, textDomain } from '../utils/i18n';
import { createApiClient } from '../utils/api';

const EMPTY_COUNTS = { all: 0, due: 0, scheduled: 0 };

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
 * Server-side processing-queue listing: filtering, pagination, run-now and cancel
 * backed by the Joinotify REST endpoints (scheduled segments source).
 *
 * @since 2.0.0
 * @param {Object} bootstrap Bootstrap payload from the queue screen.
 */
export function useProcessingQueue(bootstrap = {}) {
  const api = createApiClient(bootstrap);
  const hasApi = Boolean(bootstrap?.rest?.root);

  const loading = ref(false);
  const acting = ref('');
  const error = ref('');
  const notice = ref('');
  const items = ref(Array.isArray(bootstrap.items) ? bootstrap.items : []);
  const counts = ref(normalizeCounts(bootstrap.counts));
  const pagination = ref(normalizePagination(bootstrap.pagination));
  const workflows = ref(Array.isArray(bootstrap.workflows) ? bootstrap.workflows : []);

  const filters = ref({
    status: '',
    workflow_id: 0,
    search: '',
  });

  const statusTabs = computed(() => [
    { label: __('All', textDomain), value: '', count: counts.value.all },
    { label: __('Due', textDomain), value: 'due', count: counts.value.due },
    { label: __('Scheduled', textDomain), value: 'scheduled', count: counts.value.scheduled },
  ]);

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

    if (filters.value.status) {
      query.set('status', String(filters.value.status));
    }

    if (filters.value.workflow_id) {
      query.set('workflow_id', String(filters.value.workflow_id));
    }

    if (filters.value.search) {
      query.set('search', String(filters.value.search));
    }

    return query.toString();
  }

  function applyListPayload(response) {
    items.value = Array.isArray(response?.items) ? response.items : [];
    counts.value = normalizeCounts(response?.counts);
    pagination.value = normalizePagination(response?.pagination);
  }

  async function fetchItems() {
    if (!hasApi) {
      return;
    }

    loading.value = true;
    error.value = '';

    try {
      const response = await api.get(`/admin/queue?${buildQuery()}`);

      if (response?.status === 'error') {
        throw new Error(response.message || __('Could not load the processing queue.', textDomain));
      }

      applyListPayload(response);
    } catch (fetchError) {
      error.value = fetchError instanceof Error ? fetchError.message : __('Could not load the processing queue.', textDomain);
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

  function setWorkflowFilter(workflowId) {
    filters.value = { ...filters.value, workflow_id: Number(workflowId) || 0 };
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

  async function postAction(path, body, fallbackMessage) {
    if (!hasApi) {
      return false;
    }

    acting.value = body?.id || (body?.all ? 'all' : 'action');
    error.value = '';
    notice.value = '';

    try {
      const response = await api.post(path, body);

      if (response?.status === 'error') {
        // The endpoint still returns a fresh list alongside the error.
        applyListPayload(response);
        throw new Error(response.message || fallbackMessage);
      }

      applyListPayload(response);
      notice.value = response?.message || '';

      return true;
    } catch (actionError) {
      error.value = actionError instanceof Error ? actionError.message : fallbackMessage;

      return false;
    } finally {
      acting.value = '';
    }
  }

  function runNow(id) {
    return postAction('/admin/queue/run', { id }, __('Could not run the scheduled item.', textDomain));
  }

  function cancel(id) {
    return postAction('/admin/queue/cancel', { id }, __('Could not cancel the scheduled item.', textDomain));
  }

  function cancelAll() {
    return postAction('/admin/queue/cancel', { all: true }, __('Could not clear the queue.', textDomain));
  }

  return {
    loading,
    acting,
    error,
    notice,
    items,
    counts,
    pagination,
    workflows,
    filters,
    statusTabs,
    pageSummary,
    fetchItems,
    reload: fetchItems,
    setStatusFilter,
    setWorkflowFilter,
    setSearch,
    firstPage,
    previousPage,
    nextPage,
    lastPage,
    goToPage,
    runNow,
    cancel,
    cancelAll,
  };
}
