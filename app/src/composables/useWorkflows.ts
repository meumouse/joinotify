/**
 * useWorkflows.ts
 *
 * Composable powering the workflows listing screen. Manages loading, status
 * filtering, search, pagination, bulk selection, per-item status toggling, and
 * bulk actions against the REST API, with a mock-data fallback when no API is
 * available.
 *
 * @since 2.0.0
 */
import { computed, onMounted, ref, watch } from 'vue';
import { __, textDomain } from '../utils/i18n';
import { createApiClient } from '../utils/api';
import { useBulkSelection } from './useBulkSelection';
import { usePagination } from './usePagination';

const BULK_ACTIONS_DEFAULT = [
  { label: __('Move to trash', textDomain), value: 'trash', destructive: true },
  { label: __('Mark as active', textDomain), value: 'publish' },
  { label: __('Mark as inactive', textDomain), value: 'draft' },
];

const BULK_ACTIONS_TRASH = [
  { label: __('Restore', textDomain), value: 'restore' },
  { label: __('Delete permanently', textDomain), value: 'delete_permanently', destructive: true },
];

const MOCK_WORKFLOWS = [
  {
    id: 911,
    name: 'My automation #862022',
    created_at: '2026-03-18 18:15:09',
    status: 'publish',
    edit_url: 'admin.php?page=joinotify-workflows-builder&id=911',
    delete_url: '?page=joinotify-workflows&action=delete&id=911',
  },
  {
    id: 912,
    name: 'Abandoned cart',
    created_at: '2026-02-25 09:42:30',
    status: 'draft',
    edit_url: 'admin.php?page=joinotify-workflows-builder&id=912',
    delete_url: '?page=joinotify-workflows&action=delete&id=912',
  },
  {
    id: 913,
    name: 'Premium post-purchase',
    created_at: '2026-01-11 13:10:05',
    status: 'publish',
    edit_url: 'admin.php?page=joinotify-workflows-builder&id=913',
    delete_url: '?page=joinotify-workflows&action=delete&id=913',
  },
  {
    id: 914,
    name: 'Seasonal flow 2026',
    created_at: '2026-04-01 08:25:17',
    status: 'draft',
    edit_url: 'admin.php?page=joinotify-workflows-builder&id=914',
    delete_url: '?page=joinotify-workflows&action=delete&id=914',
  },
];

/**
 * Normalizes a workflow status, defaulting to 'publish' when unknown.
 *
 * @since 2.0.0
 * @param {string} status The raw status.
 * @returns {string} A valid status value.
 */
function normalizeStatus(status) {
  return ['publish', 'draft', 'trash'].includes(status) ? status : 'publish';
}

/**
 * Normalizes a raw workflow record, filling default fields.
 *
 * @since 2.0.0
 * @param {Object} workflow The raw workflow.
 * @returns {Object} The normalized workflow.
 */
function normalizeWorkflow(workflow) {
  return {
    id: workflow.id,
    name: workflow.name || __('Untitled workflow', textDomain),
    created_at: workflow.created_at || '',
    status: normalizeStatus(workflow.status),
    edit_url: workflow.edit_url || 'admin.php?page=joinotify-workflows-builder',
    delete_url: workflow.delete_url || '#',
    restore_url: workflow.restore_url || '',
    delete_permanently_url: workflow.delete_permanently_url || '',
    previous_status: workflow.previous_status || null,
  };
}

/**
 * Counts workflows grouped by status.
 *
 * @since 2.0.0
 * @param {Array} items The workflows.
 * @returns {Object} Counts keyed by status.
 */
function countWorkflows(items) {
  return items.reduce(
    (accumulator, workflow) => {
      accumulator[workflow.status] += 1;
      return accumulator;
    },
    { publish: 0, draft: 0, trash: 0 }
  );
}

/**
 * Clones and normalizes a list of workflows.
 *
 * @since 2.0.0
 * @param {Array} items The raw workflows.
 * @returns {Array} The cloned, normalized workflows.
 */
function cloneWorkflows(items) {
  return items.map((workflow) => normalizeWorkflow(workflow));
}

/**
 * Returns a promise that resolves after a simulated latency delay.
 *
 * @since 2.0.0
 * @param {number} [duration] Delay in milliseconds.
 * @returns {Promise<void>} A promise resolving after the delay.
 */
function simulateLatency(duration = 300) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, duration);
  });
}

/**
 * Provides state and actions for the workflows listing screen.
 *
 * @since 2.0.0
 * @param {Object} [bootstrap] Bootstrap payload from the workflows screen.
 * @returns {Object} Listing state, computed values, and action methods.
 */
export function useWorkflows(bootstrap = {}) {
  const api = createApiClient(bootstrap);
  const hasApi = Boolean(bootstrap?.rest?.root);

  const loading = ref(true);
  const error = ref('');
  const bulkActionLoading = ref(false);
  const updateLoadingIds = ref(new Set());
  const searchQuery = ref(bootstrap.search_query || '');
  const selectedStatus = ref(normalizeStatus(bootstrap.active_status));
  const baseWorkflows = cloneWorkflows(bootstrap.workflows?.length ? bootstrap.workflows : hasApi ? [] : MOCK_WORKFLOWS);
  const workflows = ref(baseWorkflows);
  const initialSnapshot = cloneWorkflows(baseWorkflows);

  const filteredWorkflows = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return workflows.value.filter((workflow) => {
      const matchesStatus = workflow.status === selectedStatus.value;
      const matchesQuery = !query || workflow.name.toLowerCase().includes(query);
      return matchesStatus && matchesQuery;
    });
  });

  const pagination = usePagination(filteredWorkflows, {
    currentPage: bootstrap.pagination?.current_page || 1,
    perPage: bootstrap.pagination?.per_page || 20,
  });

  const visibleWorkflows = computed(() => pagination.paginatedItems.value);
  const bulkSelection = useBulkSelection(visibleWorkflows);
  const counts = computed(() => countWorkflows(workflows.value));
  const totalSelected = computed(() => bulkSelection.selectedCount.value);

  const statusTabs = computed(() => [
    { label: __('Active', textDomain), value: 'publish', count: counts.value.publish },
    { label: __('Inactive', textDomain), value: 'draft', count: counts.value.draft },
    { label: __('Trash', textDomain), value: 'trash', count: counts.value.trash },
  ]);

  const bulkActionOptions = computed(() =>
    selectedStatus.value === 'trash' ? BULK_ACTIONS_TRASH : BULK_ACTIONS_DEFAULT
  );

  const totalItemsText = computed(() => `${pagination.totalItems.value} ${__('workflows', textDomain)}`);
  const pageSummary = computed(() => {
    if (!pagination.totalItems.value) {
      return __('0 results', textDomain);
    }

    const start = (pagination.currentPage.value - 1) * pagination.perPage.value + 1;
    const end = Math.min(pagination.currentPage.value * pagination.perPage.value, pagination.totalItems.value);
    return `${start}-${end} ${__('of', textDomain)} ${pagination.totalItems.value}`;
  });

  watch(selectedStatus, () => {
    bulkSelection.clearSelection();
    pagination.firstPage();
  });

  watch(searchQuery, () => {
    pagination.firstPage();
    bulkSelection.clearSelection();
  });

  /**
   * Fetches workflows from the API, or uses mock data when no API is present.
   *
   * @since 2.0.0
   * @returns {Promise<void>} Resolves once workflows are loaded.
   */
  async function fetchWorkflows() {
    if (!hasApi) {
      workflows.value = cloneWorkflows(initialSnapshot);
      await simulateLatency(bootstrap.loading_delay || 350);
      return;
    }

    const response = await api.get('/admin/workflows');

    if (response?.status === 'error') {
      throw new Error(response.message || __('Could not load workflows.', textDomain));
    }

    workflows.value = cloneWorkflows(Array.isArray(response?.workflows) ? response.workflows : []);
  }

  onMounted(async () => {
    try {
      await fetchWorkflows();
    } catch (fetchError) {
      error.value = fetchError instanceof Error ? fetchError.message : __('Could not load workflows.', textDomain);
    } finally {
      loading.value = false;
    }
  });

  /**
   * Reloads the workflow list, resetting selection and pagination.
   *
   * @since 2.0.0
   * @returns {Promise<void>} Resolves once the reload completes.
   */
  async function reload() {
    loading.value = true;
    error.value = '';
    bulkSelection.clearSelection();

    try {
      await fetchWorkflows();
    } catch (fetchError) {
      error.value = fetchError instanceof Error ? fetchError.message : __('Could not load workflows.', textDomain);
    } finally {
      pagination.firstPage();
      loading.value = false;
    }
  }

  /**
   * Sets the active status filter tab.
   *
   * @since 2.0.0
   * @param {string} status The status to filter by.
   */
  function setStatusFilter(status) {
    selectedStatus.value = normalizeStatus(status);
  }

  /**
   * Sets the search query.
   *
   * @since 2.0.0
   * @param {string} value The search string.
   */
  function setSearchQuery(value) {
    searchQuery.value = value || '';
  }

  /**
   * Finds a workflow by ID.
   *
   * @since 2.0.0
   * @param {string|number} id The workflow ID.
   * @returns {Object|undefined} The workflow, or undefined.
   */
  function findWorkflow(id) {
    return workflows.value.find((workflow) => String(workflow.id) === String(id));
  }

  /**
   * Locally updates a workflow's status, tracking its previous status.
   *
   * @since 2.0.0
   * @param {string|number} id The workflow ID.
   * @param {string} nextStatus The new status.
   */
  function setWorkflowStatus(id, nextStatus) {
    workflows.value = workflows.value.map((workflow) =>
      String(workflow.id) === String(id)
        ? {
            ...workflow,
            previous_status: workflow.status === 'trash' ? workflow.previous_status : workflow.status,
            status: normalizeStatus(nextStatus),
          }
        : workflow
    );
  }

  /**
   * Toggles (or forces) a workflow's active/inactive status via the API.
   *
   * @since 2.0.0
   * @param {string|number} id The workflow ID.
   * @param {string} [forcedStatus] Force a specific status instead of toggling.
   * @returns {Promise<void>} Resolves once the status update completes.
   */
  async function toggleWorkflowStatus(id, forcedStatus = '') {
    const workflow = findWorkflow(id);

    if (!workflow || workflow.status === 'trash' || updateLoadingIds.value.has(String(id))) {
      return;
    }

    const nextStatus = normalizeStatus(forcedStatus || (workflow.status === 'publish' ? 'draft' : 'publish'));
    const nextLoadingIds = new Set(updateLoadingIds.value);
    nextLoadingIds.add(String(id));
    updateLoadingIds.value = nextLoadingIds;

    try {
      error.value = '';

      if (hasApi) {
        const response = await api.post('/admin/workflows/status', { id, status: nextStatus });

        if (response?.status === 'error') {
          throw new Error(response.message || __('Could not update the status.', textDomain));
        }
      } else {
        await simulateLatency(280);
      }

      setWorkflowStatus(id, nextStatus);
    } catch (taskError) {
      error.value = taskError instanceof Error ? taskError.message : __('Could not update the status.', textDomain);
    } finally {
      const finishedLoadingIds = new Set(updateLoadingIds.value);
      finishedLoadingIds.delete(String(id));
      updateLoadingIds.value = finishedLoadingIds;
    }
  }

  /**
   * Applies a bulk action to the local workflow list (mock/no-API mode).
   *
   * @since 2.0.0
   * @param {string} action The bulk action key.
   * @param {Array} ids The affected workflow IDs (as strings).
   */
  function applyBulkActionLocally(action, ids) {
    if (action === 'delete_permanently') {
      workflows.value = workflows.value.filter((workflow) => !ids.includes(String(workflow.id)));
      return;
    }

    workflows.value = workflows.value.map((workflow) => {
      if (!ids.includes(String(workflow.id))) {
        return workflow;
      }

      if (action === 'restore') {
        return {
          ...workflow,
          status: normalizeStatus(workflow.previous_status || 'draft'),
          previous_status: null,
        };
      }

      if (action === 'trash') {
        return {
          ...workflow,
          previous_status: workflow.status,
          status: 'trash',
        };
      }

      return {
        ...workflow,
        previous_status: workflow.status === 'trash' ? workflow.previous_status : workflow.status,
        status: normalizeStatus(action),
      };
    });
  }

  /**
   * Applies a bulk action to the selected workflows via the API or locally.
   *
   * @since 2.0.0
   * @param {string} action The bulk action key.
   * @param {Array} [ids] The affected workflow IDs (defaults to selection).
   * @returns {Promise<void>} Resolves once the action completes.
   */
  async function applyBulkAction(action, ids = bulkSelection.selectedIds.value) {
    if (!action || !ids.length) {
      return;
    }

    bulkActionLoading.value = true;
    error.value = '';

    try {
      if (hasApi) {
        const response = await api.post('/admin/workflows/bulk', { action, ids });

        if (response?.status === 'error') {
          throw new Error(response.message || __('The bulk action failed.', textDomain));
        }

        if (Array.isArray(response?.workflows)) {
          workflows.value = cloneWorkflows(response.workflows);
        } else {
          await fetchWorkflows();
        }
      } else {
        await simulateLatency(300);
        applyBulkActionLocally(action, ids);
      }

      bulkSelection.clearSelection();
    } catch (taskError) {
      error.value = taskError instanceof Error ? taskError.message : __('The bulk action failed.', textDomain);
    } finally {
      bulkActionLoading.value = false;
    }
  }

  /**
   * Navigates the browser to a URL.
   *
   * @since 2.0.0
   * @param {string} url The destination URL.
   */
  function navigateTo(url) {
    if (!url || typeof window === 'undefined') {
      return;
    }

    window.location.href = url;
  }

  return {
    applyBulkAction,
    bulkActionLoading,
    bulkActionOptions,
    bulkSelection,
    counts,
    error,
    loading,
    navigateTo,
    pageSummary,
    pagination,
    reload,
    searchQuery,
    selectedStatus,
    setSearchQuery,
    setStatusFilter,
    statusTabs,
    totalItemsText,
    totalSelected,
    toggleWorkflowStatus,
    updateLoadingIds,
    visibleWorkflows,
    workflows,
  };
}
