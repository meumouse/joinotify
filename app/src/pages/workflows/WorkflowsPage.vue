<script setup>
/**
 * WorkflowsPage.vue
 *
 * Admin "Workflows" listing screen: renders the status tabs, search, bulk
 * actions toolbar, paginated table and single/bulk trash-delete confirmation
 * modal, backed by the useWorkflows composable for data and state.
 *
 * @since 2.0.0
 */
import { computed, reactive, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { useWorkflows } from '../../composables/useWorkflows';
import BaseButton from '../../components/base/BaseButton.vue';
import ConfirmActionModal from '../../components/workflows/ConfirmActionModal.vue';
import EmptyState from '../../components/workflows/EmptyState.vue';
import LoadingState from '../../components/workflows/LoadingState.vue';
import PageHeader from '../../components/layout/PageHeader.vue';
import StatusTabs from '../../components/workflows/StatusTabs.vue';
import TableToolbar from '../../components/workflows/TableToolbar.vue';
import WorkflowSearch from '../../components/workflows/WorkflowSearch.vue';
import WorkflowTable from '../../components/workflows/WorkflowTable.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const {
  applyBulkAction,
  bulkActionLoading,
  bulkActionOptions,
  bulkSelection,
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
} = useWorkflows(props.bootstrap);

const bulkAction = ref('');
const confirmState = reactive({
  open: false,
  title: '',
  description: '',
  action: '',
  kind: '',
  workflowId: '',
});

const createUrl = computed(() => props.bootstrap?.create_url || 'admin.php?page=joinotify-workflows-builder');
const loadingIds = computed(() => Array.from(updateLoadingIds.value));
const selectedIds = computed(() => bulkSelection.selectedIds.value);
const allVisibleSelected = computed(() => bulkSelection.isAllVisibleSelected.value);
const partiallyVisibleSelected = computed(() => bulkSelection.isPartiallyVisibleSelected.value);
const tablePagination = computed(() => ({
  current_page: pagination.currentPage.value,
  total_items: pagination.totalItems.value,
  total_pages: pagination.totalPages.value,
}));

const pageTitle = computed(() => __('Manage workflows', textDomain));
const pageDescription = computed(() =>
  __('Browse, filter and manage workflows with bulk selection, pagination and quick status switching.', textDomain)
);
const addWorkflowLabel = computed(() => __('Add new workflow', textDomain));
const searchPlaceholder = computed(() => __('Search workflows...', textDomain));
const clearSearchLabel = computed(() => __('Clear search', textDomain));
const retryLabel = computed(() => __('Try again', textDomain));
const noWorkflowsTitle = computed(() => __('No workflows found', textDomain));
const noWorkflowsDescription = computed(() =>
  __('No workflows match the current filters. Create a new workflow or switch tabs to see other results.', textDomain)
);
const deleteConfirmLabel = computed(() => __('Delete', textDomain));
const confirmLabel = computed(() => __('Confirm', textDomain));

const wordpressDateFormat = computed(() => props.bootstrap?.date_format || 'F j, Y');
const wordpressTimeFormat = computed(() => props.bootstrap?.time_format || 'g:i a');

/**
 * Zero-pad a number to two digits.
 *
 * @since 2.0.0
 * @param {number} value Value to pad.
 * @returns {string} Zero-padded string.
 */
function pad(value) {
  return String(value).padStart(2, '0');
}

/**
 * Format a date using a WordPress date-format token string (e.g. 'F j, Y').
 *
 * @since 2.0.0
 * @param {Date} date Date to format.
 * @param {string} format WordPress date-format tokens.
 * @returns {string} Formatted date part.
 */
function formatWordPressPart(date, format) {
  const monthNamesLong = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];
  const monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const dayNamesLong = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const dayNamesShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  const tokens = {
    d: () => pad(date.getDate()),
    D: () => dayNamesShort[date.getDay()],
    j: () => String(date.getDate()),
    l: () => dayNamesLong[date.getDay()],
    F: () => monthNamesLong[date.getMonth()],
    M: () => monthNamesShort[date.getMonth()],
    m: () => pad(date.getMonth() + 1),
    n: () => String(date.getMonth() + 1),
    Y: () => String(date.getFullYear()),
    y: () => String(date.getFullYear()).slice(-2),
    H: () => pad(date.getHours()),
    G: () => String(date.getHours()),
    h: () => pad(((date.getHours() + 11) % 12) + 1),
    g: () => String(((date.getHours() + 11) % 12) + 1),
    i: () => pad(date.getMinutes()),
    s: () => pad(date.getSeconds()),
    a: () => (date.getHours() >= 12 ? 'pm' : 'am'),
    A: () => (date.getHours() >= 12 ? 'PM' : 'AM'),
  };

  let escaping = false;
  let output = '';

  for (const character of String(format || '')) {
    if (escaping) {
      output += character;
      escaping = false;
      continue;
    }

    if (character === '\\') {
      escaping = true;
      continue;
    }

    output += tokens[character] ? tokens[character]() : character;
  }

  return output;
}

/**
 * Format a raw MySQL-style datetime string using the site's date/time formats.
 *
 * @since 2.0.0
 * @param {string} value Raw datetime value (e.g. '2026-07-01 10:00:00').
 * @returns {string} Formatted date and time, or a fallback dash/raw value.
 */
function formatDate(value) {
  if (!value) {
    return '-';
  }

  const parsed = new Date(String(value).replace(' ', 'T'));

  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  const date = formatWordPressPart(parsed, wordpressDateFormat.value);
  const time = formatWordPressPart(parsed, wordpressTimeFormat.value);

  return [date, time].filter(Boolean).join(' ').trim();
}

/**
 * Reset the trash/delete confirmation modal state.
 *
 * @since 2.0.0
 */
function resetConfirmation() {
  confirmState.open = false;
  confirmState.title = '';
  confirmState.description = '';
  confirmState.action = '';
  confirmState.kind = '';
  confirmState.workflowId = '';
}

/**
 * Apply the pending bulk/single action from the confirmation modal.
 *
 * @since 2.0.0
 */
function confirmSelectionAction() {
  if (!confirmState.action) {
    return;
  }

  applyBulkAction(confirmState.action, confirmState.kind === 'bulk' ? undefined : [confirmState.workflowId]).finally(() => {
    resetConfirmation();
    bulkAction.value = '';
  });
}

/**
 * Apply a bulk action, opening the confirmation modal first when destructive.
 *
 * @since 2.0.0
 * @param {string} action Bulk action value (e.g. 'trash', 'delete_permanently').
 */
function openBulkConfirmation(action) {
  if (!bulkSelection.selectedIds.value.length || !action) {
    return;
  }

  const option = bulkActionOptions.value.find((item) => item.value === action);

  if (option?.destructive) {
    confirmState.open = true;
    confirmState.kind = 'bulk';
    confirmState.action = action;
    confirmState.title = action === 'delete_permanently' ? __('Delete permanently', textDomain) : __('Move to trash', textDomain);
    confirmState.description =
      action === 'delete_permanently'
        ? __('The selected workflows will be removed permanently and this action cannot be undone.', textDomain)
        : __('The selected workflows will be moved to trash and can be restored later.', textDomain);
    return;
  }

  applyBulkAction(action).finally(() => {
    bulkAction.value = '';
  });
}

/**
 * Select or deselect all currently visible workflows.
 *
 * @since 2.0.0
 * @param {boolean} checked Whether to select (true) or clear (false) the visible set.
 */
function handleSelectAll(checked) {
  bulkSelection.setVisibleSelected(visibleWorkflows.value, checked);
}

/**
 * Select or deselect a single workflow row.
 *
 * @since 2.0.0
 * @param {Object} workflow Workflow row being toggled.
 * @param {boolean} checked Whether the row is now selected.
 */
function handleRowSelect(workflow, checked) {
  bulkSelection.setSelected(workflow.id, checked);
}

/**
 * Navigate to the builder to edit a workflow.
 *
 * @since 2.0.0
 * @param {Object} workflow Workflow to edit.
 */
function handleEdit(workflow) {
  navigateTo(workflow.edit_url);
}

/**
 * Open the confirmation modal to move a single workflow to trash.
 *
 * @since 2.0.0
 * @param {Object} workflow Workflow to trash.
 */
function handleTrash(workflow) {
  confirmState.open = true;
  confirmState.kind = 'single';
  confirmState.action = 'trash';
  confirmState.workflowId = String(workflow.id);
  confirmState.title = __('Move to trash', textDomain);
  confirmState.description = `${__('The workflow', textDomain)} "${workflow.name}" ${__('will be moved to trash.', textDomain)}`;
}

/**
 * Restore a trashed workflow.
 *
 * @since 2.0.0
 * @param {Object} workflow Workflow to restore.
 */
function handleRestore(workflow) {
  applyBulkAction('restore', [String(workflow.id)]);
}

/**
 * Open the confirmation modal to permanently delete a single workflow.
 *
 * @since 2.0.0
 * @param {Object} workflow Workflow to delete.
 */
function handleDeletePermanent(workflow) {
  confirmState.open = true;
  confirmState.kind = 'single';
  confirmState.action = 'delete_permanently';
  confirmState.workflowId = String(workflow.id);
  confirmState.title = __('Delete permanently', textDomain);
  confirmState.description = `${__('The workflow', textDomain)} "${workflow.name}" ${__('will be removed permanently.', textDomain)}`;
}

/**
 * Toggle a workflow between active and inactive status.
 *
 * @since 2.0.0
 * @param {Object} workflow Workflow being toggled.
 * @param {string} nextStatus Status to switch to.
 */
function handleToggleStatus(workflow, nextStatus) {
  if (workflow.status === 'trash' || nextStatus === workflow.status) {
    return;
  }

  toggleWorkflowStatus(workflow.id, nextStatus);
}
</script>

<template>
  <div class="joinotify-settings min-h-screen p-4">
    <div class="w-full">
      <PageHeader
        :action-label="addWorkflowLabel"
        :action-href="createUrl"
        :description="pageDescription"
        :loading="loading"
        :title="pageTitle"
      />

      <div class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
        <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6 lg:px-10 lg:py-8">
          <StatusTabs
            :active-status="selectedStatus"
            :tabs="statusTabs"
            @select="setStatusFilter"
          />

          <WorkflowSearch
            :model-value="searchQuery"
            :clear-label="clearSearchLabel"
            :placeholder="searchPlaceholder"
            @clear="setSearchQuery('')"
            @update:modelValue="setSearchQuery"
          />

          <div v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p>{{ error }}</p>
              <BaseButton :title="retryLabel" variant="secondary" @click="reload" />
            </div>
          </div>

          <LoadingState v-if="loading" />

          <template v-else>
            <TableToolbar
              :bulk-action="bulkAction"
              :bulk-options="bulkActionOptions"
              :bulk-disabled="!totalSelected"
              :loading="bulkActionLoading"
              :pagination="tablePagination"
              :pagination-disabled="loading || bulkActionLoading"
              :selected-count="totalSelected"
              :summary="pageSummary"
              @applyBulkAction="openBulkConfirmation(bulkAction)"
              @first="pagination.firstPage"
              @last="pagination.lastPage"
              @next="pagination.nextPage"
              @previous="pagination.previousPage"
              @update:bulkAction="bulkAction = $event"
            />

            <WorkflowTable
              v-if="visibleWorkflows.length"
              :all-selected="allVisibleSelected"
              :format-date="formatDate"
              :indeterminate="partiallyVisibleSelected"
              :loading-ids="loadingIds"
              :selected-ids="selectedIds"
              :workflows="visibleWorkflows"
              @deletePermanent="handleDeletePermanent"
              @edit="handleEdit"
              @restore="handleRestore"
              @select="handleRowSelect"
              @toggleAll="handleSelectAll"
              @toggleStatus="handleToggleStatus"
              @trash="handleTrash"
            />

            <EmptyState
              v-else
              :action-href="createUrl"
              :action-label="addWorkflowLabel"
              :description="noWorkflowsDescription"
              :title="noWorkflowsTitle"
            />

            <TableToolbar
              :bulk-action="bulkAction"
              :bulk-options="bulkActionOptions"
              :bulk-disabled="!totalSelected"
              :loading="bulkActionLoading"
              :pagination="tablePagination"
              :pagination-disabled="loading || bulkActionLoading"
              :selected-count="totalSelected"
              :summary="`${totalItemsText} | ${pageSummary}`"
              @applyBulkAction="openBulkConfirmation(bulkAction)"
              @first="pagination.firstPage"
              @last="pagination.lastPage"
              @next="pagination.nextPage"
              @previous="pagination.previousPage"
              @update:bulkAction="bulkAction = $event"
            />
          </template>
        </div>
      </div>
    </div>

    <ConfirmActionModal
      :confirm-label="confirmState.action === 'delete_permanently' ? deleteConfirmLabel : confirmLabel"
      :description="confirmState.description"
      :loading="bulkActionLoading"
      :open="confirmState.open"
      :title="confirmState.title"
      @cancel="resetConfirmation"
      @confirm="confirmSelectionAction"
    />
  </div>
</template>
