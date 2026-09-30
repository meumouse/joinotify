<script setup>
/**
 * SuppressionsTab.vue — the suppression list: phones that never get a
 * marketing campaign, whatever their consent. Lists them by reason, adds a
 * phone by hand and takes one off, asking twice for an opt-out or a Meta
 * marketing block.
 *
 * @since 2.5.0
 */
import { computed, onMounted, ref } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { useContactsContext } from '../context';
import { formatDate, formatPhone, labelOf } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import ConfirmActionModal from '../../../components/workflows/ConfirmActionModal.vue';

const { api, bootstrap, canWrite, toast, notifyError } = useContactsContext();

const inputClass =
  'rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const items = ref([]);
const loading = ref(false);
const error = ref('');
const reason = ref('');
const search = ref('');
const page = ref(1);
const totalPages = ref(1);
const identity = ref('');
const note = ref('');
const adding = ref(false);
const target = ref(null);
const removing = ref(false);
let searchTimer = 0;

const locale = computed(() => bootstrap.locale || '');

const reasons = [
  { value: 'opt_out', label: __('Opted out', textDomain) },
  { value: 'invalid_number', label: __('Invalid number', textDomain) },
  { value: 'meta_marketing_block', label: __('Blocked by Meta for marketing', textDomain) },
  { value: 'manual', label: __('Added by hand', textDomain) },
];
const reasonOptions = [{ value: '', label: __('Any reason', textDomain) }, ...reasons];

// Removing these takes a second yes: the person asked to leave, or Meta blocks marketing to them.
const needsConfirm = computed(() => ['opt_out', 'meta_marketing_block'].includes(target.value?.reason));

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.listSuppressions({ page: page.value, per_page: 25, reason: reason.value, search: search.value });

    items.value = response.items || [];
    totalPages.value = response.pagination?.total_pages || 1;
  } catch (caught) {
    error.value = caught?.message || __('Could not load the suppression list.', textDomain);
  } finally {
    loading.value = false;
  }
}

function setReason(value) {
  reason.value = value;
  page.value = 1;
  load();
}

function onSearch(event) {
  search.value = event.target.value;
  page.value = 1;
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(load, 350);
}

async function add() {
  adding.value = true;

  try {
    const response = await api.addSuppression(identity.value, note.value);

    toast(response.message, 'success', __('Suppression list', textDomain));
    identity.value = '';
    note.value = '';
    load();
  } catch (caught) {
    notifyError(caught);
  } finally {
    adding.value = false;
  }
}

async function remove() {
  removing.value = true;

  try {
    const response = await api.deleteSuppression(target.value.id, needsConfirm.value);

    toast(response.message, 'success', __('Suppression list', textDomain));
    target.value = null;
    load();
  } catch (caught) {
    notifyError(caught);
  } finally {
    removing.value = false;
  }
}

function identityLabel(value) {
  if (!value) {
    return __('(erased contact)', textDomain);
  }

  // Phones are stored as digits; a BSUID shows as it is.
  return /^\d+$/.test(value) ? formatPhone(value) : value;
}

function goTo(next) {
  page.value = Math.min(Math.max(1, next), totalPages.value);
  load();
}

onMounted(load);
</script>

<template>
  <div class="mt-6 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
    <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6 lg:px-8 lg:py-6">
      <p class="max-w-3xl text-[13px] leading-6 text-slate-500">
        {{ __('Numbers on this list never receive a marketing campaign, whatever their consent. Workflow messages are not affected. Opt-outs land here on their own.', textDomain) }}
      </p>

      <form v-if="canWrite" class="flex flex-col gap-3 rounded-[8px] bg-slate-50 p-4 lg:flex-row lg:items-end" @submit.prevent="add">
        <label class="flex flex-1 flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('Phone', textDomain) }}</span>
          <input v-model="identity" :class="inputClass" placeholder="+55 41 98711-1527" required type="text" />
        </label>
        <label class="flex flex-1 flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('Note (optional)', textDomain) }}</span>
          <input v-model="note" :class="inputClass" maxlength="300" type="text" />
        </label>
        <BaseButton :disabled="identity.trim().length < 3" :loading="adding" :title="__('Add to the list', textDomain)" type="submit" />
      </form>

      <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div class="flex flex-1 flex-col lg:max-w-sm">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Search by phone', textDomain) }}</label>
          <input :class="inputClass" :value="search" type="search" :placeholder="__('Type to filter…', textDomain)" @input="onSearch" />
        </div>
        <div class="flex w-full flex-col sm:w-64">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Reason', textDomain) }}</label>
          <BaseListboxSelect :model-value="reason" :options="reasonOptions" @update:model-value="setReason" />
        </div>
      </div>

      <div v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p>{{ error }}</p>
          <BaseButton :title="__('Try again', textDomain)" variant="secondary" @click="load" />
        </div>
      </div>

      <div v-if="loading && !items.length" class="py-16 text-center text-[14px] text-slate-400">{{ __('Loading…', textDomain) }}</div>

      <div v-else-if="items.length" class="overflow-x-auto" :class="loading ? 'opacity-60' : ''">
        <table class="min-w-full divide-y divide-slate-100 text-left">
          <thead>
            <tr class="text-[12px] uppercase tracking-wide text-slate-400">
              <th class="px-3 py-3 font-medium">{{ __('Phone', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Reason', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Note', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Since', textDomain) }}</th>
              <th v-if="canWrite" class="px-3 py-3 text-right font-medium">{{ __('Actions', textDomain) }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-[14px] text-slate-700">
            <tr v-for="row in items" :key="row.id">
              <td class="whitespace-nowrap px-3 py-3">{{ identityLabel(row.identity) }}</td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ labelOf(reasons, row.reason) }}</td>
              <td class="px-3 py-3 text-slate-500">{{ row.note || '—' }}</td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ formatDate(row.createdAt, locale) }}</td>
              <td v-if="canWrite" class="whitespace-nowrap px-3 py-3 text-right">
                <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-rose-600 hover:bg-rose-50" @click="target = row">
                  {{ __('Remove', textDomain) }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="!error" class="py-16 text-center">
        <p class="text-[15px] font-medium text-slate-600">{{ __('The suppression list is empty', textDomain) }}</p>
      </div>

      <div v-if="totalPages > 1" class="flex items-center justify-end gap-1 pt-2">
        <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 hover:bg-slate-100 disabled:opacity-40" :disabled="page <= 1" @click="goTo(page - 1)">‹</button>
        <span class="px-3 text-[13px] text-slate-500">{{ page }} / {{ totalPages }}</span>
        <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 hover:bg-slate-100 disabled:opacity-40" :disabled="page >= totalPages" @click="goTo(page + 1)">›</button>
      </div>
    </div>

    <ConfirmActionModal
      :confirm-label="__('Remove from the list', textDomain)"
      :description="needsConfirm
        ? __('This person asked to stop receiving messages, or Meta blocks marketing to them. Taking the number off the list does not opt them in: they stay opted out until they agree again.', textDomain)
        : __('The number can receive marketing campaigns again if it has consent.', textDomain)"
      :loading="removing"
      :open="Boolean(target)"
      :title="__('Remove from the suppression list?', textDomain)"
      @cancel="target = null"
      @confirm="remove"
    />
  </div>
</template>
