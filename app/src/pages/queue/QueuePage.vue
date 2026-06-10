<script setup>
/**
 * QueuePage.vue — scheduled segments ("processing queue") listing.
 *
 * Lists workflow continuations waiting on a delay (Action Scheduler / WP-Cron),
 * letting the user dispatch one immediately (skip the wait) or cancel it.
 *
 * @since 2.0.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { useProcessingQueue } from '../../composables/useProcessingQueue';
import BaseButton from '../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../components/base/BaseListboxSelect.vue';
import ConfirmActionModal from '../../components/workflows/ConfirmActionModal.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const {
  loading,
  acting,
  error,
  notice,
  items,
  pagination,
  workflows,
  filters,
  statusTabs,
  pageSummary,
  reload,
  setStatusFilter,
  setWorkflowFilter,
  setSearch,
  firstPage,
  previousPage,
  nextPage,
  lastPage,
  runNow,
  cancel,
  cancelAll,
} = useProcessingQueue(props.bootstrap);

const searchTerm = ref('');
const confirmOpen = ref(false);
const confirmKind = ref('');
const confirmTarget = ref(null);

const backendLabel = computed(() =>
  (props.bootstrap?.backend ?? 'wp_cron') === 'action_scheduler' ? 'Action Scheduler' : 'WP-Cron'
);

function applySearch(event) {
  searchTerm.value = event.target.value;
  setSearch(searchTerm.value);
}

function clearFilters() {
  searchTerm.value = '';
  setStatusFilter('');
  setWorkflowFilter(0);
  setSearch('');
}

function nextActionLabel(entry) {
  if (entry.next_action_label) {
    return entry.next_action_label;
  }

  if (entry.next_action) {
    return entry.next_action;
  }

  return __('End of workflow', textDomain);
}

function askRun(entry) {
  confirmKind.value = 'run';
  confirmTarget.value = entry;
  confirmOpen.value = true;
}

function askCancel(entry) {
  confirmKind.value = 'cancel';
  confirmTarget.value = entry;
  confirmOpen.value = true;
}

function askCancelAll() {
  confirmKind.value = 'cancel-all';
  confirmTarget.value = null;
  confirmOpen.value = true;
}

function runConfirm() {
  let promise;

  if (confirmKind.value === 'run') {
    promise = runNow(confirmTarget.value?.id);
  } else if (confirmKind.value === 'cancel') {
    promise = cancel(confirmTarget.value?.id);
  } else {
    promise = cancelAll();
  }

  Promise.resolve(promise).finally(() => {
    confirmOpen.value = false;
    confirmKind.value = '';
    confirmTarget.value = null;
  });
}

const confirmTitle = computed(() => {
  if (confirmKind.value === 'run') {
    return __('Dispatch now', textDomain);
  }

  if (confirmKind.value === 'cancel-all') {
    return __('Cancel all scheduled items', textDomain);
  }

  return __('Cancel scheduled item', textDomain);
});

const confirmDescription = computed(() => {
  if (confirmKind.value === 'run') {
    return __('The remaining actions will be executed right now, skipping the scheduled wait. This cannot be undone.', textDomain);
  }

  if (confirmKind.value === 'cancel-all') {
    return __('Every pending scheduled item will be removed and will never run. This cannot be undone.', textDomain);
  }

  return __('This scheduled item will be removed and will never run. This cannot be undone.', textDomain);
});

const confirmLabel = computed(() =>
  confirmKind.value === 'run' ? __('Dispatch now', textDomain) : __('Cancel item', textDomain)
);
</script>

<template>
  <div class="joinotify-settings min-h-screen p-4">
    <div class="w-full">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-[22px] font-semibold text-slate-800">{{ __('Processing queue', textDomain) }}</h1>
          <p class="mt-1 max-w-2xl text-[13px] leading-5 text-slate-500">
            {{ __('Workflow steps waiting on a delay before they run. Dispatch one now to skip the wait, or cancel it to stop the processing.', textDomain) }}
          </p>
        </div>
        <span class="self-start rounded-full bg-slate-100 px-3 py-1 text-[12px] font-medium text-slate-500">
          {{ __('Scheduler', textDomain) }}: {{ backendLabel }}
        </span>
      </div>

      <div class="mt-6 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
        <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6 lg:px-8 lg:py-6">
          <!-- Status tabs -->
          <div class="flex flex-wrap gap-2">
            <button
              v-for="tab in statusTabs"
              :key="tab.value"
              type="button"
              class="rounded-full px-4 py-1.5 text-[13px] font-medium transition"
              :class="filters.status === tab.value ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
              @click="setStatusFilter(tab.value)"
            >
              {{ tab.label }}
              <span class="ml-1 opacity-70">{{ tab.count }}</span>
            </button>
          </div>

          <!-- Filters -->
          <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
            <div class="flex w-full flex-col sm:w-64">
              <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Workflow', textDomain) }}</label>
              <BaseListboxSelect
                :model-value="filters.workflow_id"
                :options="workflows"
                @update:model-value="setWorkflowFilter"
              />
            </div>

            <div class="flex flex-1 flex-col">
              <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Search workflow, recipient or action', textDomain) }}</label>
              <input
                :value="searchTerm"
                type="search"
                :placeholder="__('Type to filter…', textDomain)"
                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary-400 focus:outline-none"
                @input="applySearch"
              />
            </div>

            <button
              type="button"
              class="rounded-[8px] border border-slate-200 bg-white px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50"
              @click="clearFilters"
            >
              {{ __('Clear filters', textDomain) }}
            </button>
          </div>

          <!-- Notice -->
          <div v-if="notice" class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ notice }}
          </div>

          <!-- Error -->
          <div v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p>{{ error }}</p>
              <BaseButton :title="__('Try again', textDomain)" variant="secondary" @click="reload" />
            </div>
          </div>

          <!-- Toolbar -->
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button
              type="button"
              class="rounded-[8px] border border-slate-200 px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading || !pagination.total_items"
              @click="askCancelAll"
            >
              {{ __('Cancel all', textDomain) }}
            </button>

            <div class="flex items-center gap-3">
              <button
                type="button"
                class="rounded-[8px] border border-slate-200 px-3 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                :disabled="loading"
                @click="reload"
              >
                {{ __('Refresh', textDomain) }}
              </button>
              <span class="text-[13px] text-slate-500">{{ pageSummary }}</span>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="py-16 text-center text-[14px] text-slate-400">
            {{ __('Loading…', textDomain) }}
          </div>

          <!-- Table -->
          <div v-else-if="items.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left">
              <thead>
                <tr class="text-[12px] uppercase tracking-wide text-slate-400">
                  <th class="px-3 py-3 font-medium">{{ __('Workflow', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Scheduled for', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Next action', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Recipient', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Status', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium text-right">{{ __('Actions', textDomain) }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 text-[14px] text-slate-700">
                <tr v-for="entry in items" :key="entry.id" class="transition hover:bg-slate-50">
                  <td class="px-3 py-3">
                    <a
                      v-if="entry.workflow_edit_url"
                      :href="entry.workflow_edit_url"
                      class="font-medium text-primary-700 hover:underline"
                    >
                      {{ entry.workflow_title || ('#' + entry.workflow_id) }}
                    </a>
                    <span v-else class="font-medium">{{ entry.workflow_title || ('#' + entry.workflow_id) }}</span>
                    <span v-if="entry.pending_count > 1" class="ml-1 text-[12px] text-slate-400">
                      (+{{ entry.pending_count - 1 }} {{ __('more', textDomain) }})
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-slate-500">
                    {{ entry.scheduled_at || '—' }}
                    <span v-if="entry.delay_label" class="block text-[12px] text-slate-400">{{ entry.delay_label }}</span>
                  </td>
                  <td class="max-w-[280px] px-3 py-3 text-slate-500">{{ nextActionLabel(entry) }}</td>
                  <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ entry.receiver || '—' }}</td>
                  <td class="whitespace-nowrap px-3 py-3">
                    <span
                      class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-medium ring-1 ring-inset"
                      :class="entry.is_due ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-sky-50 text-sky-700 ring-sky-200'"
                    >
                      {{ entry.is_due ? __('Due', textDomain) : __('Scheduled', textDomain) }}
                    </span>
                    <span v-if="!entry.workflow_published" class="ml-1 inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-[12px] font-medium text-rose-600 ring-1 ring-inset ring-rose-200">
                      {{ __('Inactive', textDomain) }}
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        class="rounded-[8px] bg-primary-600 px-3 py-1.5 text-[13px] font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!!acting || !entry.workflow_published"
                        :title="!entry.workflow_published ? __('The workflow is not published.', textDomain) : __('Dispatch now', textDomain)"
                        @click="askRun(entry)"
                      >
                        {{ __('Dispatch now', textDomain) }}
                      </button>
                      <button
                        type="button"
                        class="rounded-[8px] border border-rose-200 px-3 py-1.5 text-[13px] font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!!acting"
                        @click="askCancel(entry)"
                      >
                        {{ __('Cancel', textDomain) }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty -->
          <div v-else class="py-16 text-center">
            <p class="text-[15px] font-medium text-slate-600">{{ __('Nothing in the queue', textDomain) }}</p>
            <p class="mt-1 text-[13px] text-slate-400">{{ __('No workflow steps are currently waiting to run.', textDomain) }}</p>
          </div>

          <!-- Pagination -->
          <div v-if="items.length" class="flex items-center justify-between pt-2">
            <span class="text-[13px] text-slate-500">{{ pageSummary }}</span>
            <div class="flex items-center gap-1">
              <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="firstPage">«</button>
              <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="previousPage">‹</button>
              <span class="px-3 text-[13px] text-slate-500">{{ pagination.current_page }} / {{ pagination.total_pages }}</span>
              <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page >= pagination.total_pages" @click="nextPage">›</button>
              <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page >= pagination.total_pages" @click="lastPage">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ConfirmActionModal
      :confirm-label="confirmLabel"
      :description="confirmDescription"
      :loading="!!acting"
      :open="confirmOpen"
      :title="confirmTitle"
      @cancel="confirmOpen = false"
      @confirm="runConfirm"
    />
  </div>
</template>
