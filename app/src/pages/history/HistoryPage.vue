<script setup>
/**
 * HistoryPage.vue — message dispatch history listing.
 *
 * @since 2.0.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { useMessageHistory } from '../../composables/useMessageHistory';
import BaseButton from '../../components/base/BaseButton.vue';
import ConfirmActionModal from '../../components/workflows/ConfirmActionModal.vue';
import HistoryDetailsModal from './components/HistoryDetailsModal.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const {
  loading,
  error,
  items,
  pagination,
  sources,
  filters,
  selectedIds,
  statusTabs,
  totalSelected,
  allVisibleSelected,
  pageSummary,
  reload,
  setStatusFilter,
  setSourceFilter,
  setDateRange,
  setSearch,
  firstPage,
  previousPage,
  nextPage,
  lastPage,
  toggleSelected,
  toggleSelectAll,
  removeSelected,
  clearAll,
} = useMessageHistory(props.bootstrap);

const detailsEntry = ref(null);
const detailsOpen = ref(false);
const confirmOpen = ref(false);
const confirmKind = ref('');

const dateFrom = ref('');
const dateTo = ref('');
const searchTerm = ref('');

const enabled = computed(() => (props.bootstrap?.enabled ?? 'yes') === 'yes');

const statusLabels = computed(() => ({
  sent: __('Sent', textDomain),
  failed: __('Failed', textDomain),
  queued: __('Queued', textDomain),
}));

const sourceLabels = computed(() => {
  const map = {};
  (sources.value || []).forEach((option) => {
    if (option && option.value) {
      map[option.value] = option.label;
    }
  });
  return map;
});

const typeLabels = computed(() => ({
  text: __('Text', textDomain),
  media: __('Media', textDomain),
  audio: __('Audio', textDomain),
}));

function statusBadgeClass(status) {
  if (status === 'sent') {
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
  }

  if (status === 'queued') {
    return 'bg-amber-50 text-amber-700 ring-amber-200';
  }

  return 'bg-rose-50 text-rose-700 ring-rose-200';
}

function contentPreview(value) {
  const text = String(value || '').replace(/\s+/g, ' ').trim();

  if (!text) {
    return '—';
  }

  return text.length > 70 ? `${text.slice(0, 70)}…` : text;
}

function openDetails(entry) {
  detailsEntry.value = entry;
  detailsOpen.value = true;
}

function applySearch(event) {
  searchTerm.value = event.target.value;
  setSearch(searchTerm.value);
}

function applyDateRange() {
  setDateRange(dateFrom.value, dateTo.value);
}

function clearFilters() {
  dateFrom.value = '';
  dateTo.value = '';
  searchTerm.value = '';
  setStatusFilter('');
  setSourceFilter('');
  setDateRange('', '');
  setSearch('');
}

function askConfirm(kind) {
  confirmKind.value = kind;
  confirmOpen.value = true;
}

function runConfirm() {
  const promise = confirmKind.value === 'clear' ? clearAll() : removeSelected();

  Promise.resolve(promise).finally(() => {
    confirmOpen.value = false;
    confirmKind.value = '';
  });
}

const confirmTitle = computed(() =>
  confirmKind.value === 'clear' ? __('Clear all history', textDomain) : __('Delete selected records', textDomain)
);
const confirmDescription = computed(() =>
  confirmKind.value === 'clear'
    ? __('Every history record will be permanently removed. This action cannot be undone.', textDomain)
    : __('The selected records will be permanently removed. This action cannot be undone.', textDomain)
);
</script>

<template>
  <div class="joinotify-settings min-h-screen p-4">
    <div class="w-full">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-[22px] font-semibold text-slate-800">{{ __('Message history', textDomain) }}</h1>
          <p class="mt-1 max-w-2xl text-[13px] leading-5 text-slate-500">
            {{ __('Audit every WhatsApp message dispatched by Joinotify, including workflow sends, retries and test messages.', textDomain) }}
          </p>
        </div>
      </div>

      <div
        v-if="!enabled"
        class="mt-4 rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800"
      >
        {{ __('Message history recording is disabled. Enable it in Settings → About to start logging new messages.', textDomain) }}
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
            <div class="flex flex-col">
              <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Source', textDomain) }}</label>
              <select
                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary-400 focus:outline-none"
                :value="filters.source"
                @change="setSourceFilter($event.target.value)"
              >
                <option v-for="option in sources" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </div>

            <div class="flex flex-col">
              <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('From', textDomain) }}</label>
              <input
                v-model="dateFrom"
                type="date"
                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary-400 focus:outline-none"
                @change="applyDateRange"
              />
            </div>

            <div class="flex flex-col">
              <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('To', textDomain) }}</label>
              <input
                v-model="dateTo"
                type="date"
                class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary-400 focus:outline-none"
                @change="applyDateRange"
              />
            </div>

            <div class="flex flex-1 flex-col">
              <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Search recipient or sender', textDomain) }}</label>
              <input
                :value="searchTerm"
                type="search"
                :placeholder="__('5541987111527', textDomain)"
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

          <!-- Error -->
          <div v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p>{{ error }}</p>
              <BaseButton :title="__('Try again', textDomain)" variant="secondary" @click="reload" />
            </div>
          </div>

          <!-- Toolbar -->
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <button
                type="button"
                class="rounded-[8px] bg-rose-50 px-4 py-2 text-[13px] font-semibold text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!totalSelected || loading"
                @click="askConfirm('selected')"
              >
                {{ __('Delete selected', textDomain) }}<span v-if="totalSelected"> ({{ totalSelected }})</span>
              </button>
              <button
                type="button"
                class="rounded-[8px] border border-slate-200 px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="loading || !pagination.total_items"
                @click="askConfirm('clear')"
              >
                {{ __('Clear all', textDomain) }}
              </button>
            </div>

            <span class="text-[13px] text-slate-500">{{ pageSummary }}</span>
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
                  <th class="px-3 py-3">
                    <input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll($event.target.checked)" />
                  </th>
                  <th class="px-3 py-3 font-medium">{{ __('Date', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Recipient', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Sender', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Type', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Source', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Status', textDomain) }}</th>
                  <th class="px-3 py-3 font-medium">{{ __('Content', textDomain) }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 text-[14px] text-slate-700">
                <tr
                  v-for="entry in items"
                  :key="entry.id"
                  class="cursor-pointer transition hover:bg-slate-50"
                  @click="openDetails(entry)"
                >
                  <td class="px-3 py-3" @click.stop>
                    <input
                      type="checkbox"
                      :checked="selectedIds.has(String(entry.id))"
                      @change="toggleSelected(entry.id, $event.target.checked)"
                    />
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ entry.created_at || '—' }}</td>
                  <td class="whitespace-nowrap px-3 py-3 font-medium">{{ entry.receiver || '—' }}</td>
                  <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ entry.sender || '—' }}</td>
                  <td class="whitespace-nowrap px-3 py-3">{{ typeLabels[entry.message_type] || entry.message_type }}</td>
                  <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ sourceLabels[entry.source] || entry.source }}</td>
                  <td class="whitespace-nowrap px-3 py-3">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-medium ring-1 ring-inset" :class="statusBadgeClass(entry.status)">
                      {{ statusLabels[entry.status] || entry.status }}
                    </span>
                  </td>
                  <td class="max-w-[280px] px-3 py-3 text-slate-500">{{ contentPreview(entry.content) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Empty -->
          <div v-else class="py-16 text-center">
            <p class="text-[15px] font-medium text-slate-600">{{ __('No messages found', textDomain) }}</p>
            <p class="mt-1 text-[13px] text-slate-400">{{ __('No history records match the current filters.', textDomain) }}</p>
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

    <HistoryDetailsModal
      :open="detailsOpen"
      :entry="detailsEntry || {}"
      :status-labels="statusLabels"
      :source-labels="sourceLabels"
      @close="detailsOpen = false"
    />

    <ConfirmActionModal
      :confirm-label="__('Delete', textDomain)"
      :description="confirmDescription"
      :loading="loading"
      :open="confirmOpen"
      :title="confirmTitle"
      @cancel="confirmOpen = false"
      @confirm="runConfirm"
    />
  </div>
</template>
