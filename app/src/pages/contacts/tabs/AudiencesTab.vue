<script setup>
/**
 * AudiencesTab.vue — the account's saved audiences: search, archived ones,
 * the last count of each, and the actions to open its contacts, edit,
 * archive, restore or erase it.
 *
 * @since 2.5.0
 */
import { computed, onMounted, ref } from 'vue';
import { __, sprintf, textDomain } from '../../../utils/i18n';
import { useContactsContext } from '../context';
import { formatDate } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import ConfirmActionModal from '../../../components/workflows/ConfirmActionModal.vue';
import AudienceEditor from '../components/AudienceEditor.vue';

const { api, bootstrap, canWrite, toast, notifyError, navigate } = useContactsContext();

const items = ref([]);
const loading = ref(false);
const error = ref('');
const search = ref('');
const archived = ref(false);
const page = ref(1);
const totalPages = ref(1);
const editor = ref({ open: false, audience: null });
const confirmTarget = ref(null);
const acting = ref(false);
let searchTimer = 0;

const locale = computed(() => bootstrap.locale || '');

async function load() {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.listAudiences({ page: page.value, per_page: 25, search: search.value, archived: archived.value ? 1 : '' });

    items.value = response.items || [];
    totalPages.value = response.pagination?.total_pages || 1;
  } catch (caught) {
    error.value = caught?.message || __('Could not load the audiences.', textDomain);
  } finally {
    loading.value = false;
  }
}

function onSearch(event) {
  search.value = event.target.value;
  page.value = 1;
  window.clearTimeout(searchTimer);
  searchTimer = window.setTimeout(load, 350);
}

function toggleArchived() {
  archived.value = !archived.value;
  page.value = 1;
  load();
}

function goTo(target) {
  page.value = Math.min(Math.max(1, target), totalPages.value);
  load();
}

async function openEditor(audience) {
  if (!audience) {
    editor.value = { open: true, audience: null };
    return;
  }

  // The list carries the filter already; the detail recounts, which the editor's preview does anyway.
  editor.value = { open: true, audience };
}

function onSaved() {
  editor.value = { open: false, audience: null };
  load();
}

async function setArchived(audience, value) {
  try {
    await api.saveAudience(audience.id, { archived: value });
    toast(value ? __('Audience archived.', textDomain) : __('Audience restored.', textDomain), 'success', __('Audiences', textDomain));
    load();
  } catch (caught) {
    notifyError(caught);
  }
}

async function erase() {
  acting.value = true;

  try {
    const response = await api.deleteAudience(confirmTarget.value.id);

    toast(response.message, 'success', __('Audiences', textDomain));
    confirmTarget.value = null;
    load();
  } catch (caught) {
    notifyError(caught);
  } finally {
    acting.value = false;
  }
}

function openContacts(audience) {
  navigate('contacts', { audience_id: audience.id, audience_name: audience.name });
}

onMounted(load);
</script>

<template>
  <div class="mt-6 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
    <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6 lg:px-8 lg:py-6">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-1 flex-col lg:max-w-md">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Search by name', textDomain) }}</label>
          <input
            :value="search"
            type="search"
            :placeholder="__('Type to filter…', textDomain)"
            class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none"
            @input="onSearch"
          />
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="rounded-full px-4 py-1.5 text-[13px] font-medium transition"
            :class="archived ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="toggleArchived"
          >
            {{ __('Archived', textDomain) }}
          </button>
          <BaseButton v-if="canWrite" :title="__('New audience', textDomain)" @click="openEditor(null)" />
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
              <th class="px-3 py-3 font-medium">{{ __('Audience', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Contacts', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Updated', textDomain) }}</th>
              <th class="px-3 py-3 text-right font-medium">{{ __('Actions', textDomain) }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-[14px] text-slate-700">
            <tr v-for="audience in items" :key="audience.id" class="transition hover:bg-slate-50">
              <td class="px-3 py-3">
                <span class="block font-medium text-slate-800">{{ audience.name }}</span>
                <span v-if="audience.description" class="block text-[12px] text-slate-400">{{ audience.description }}</span>
              </td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">
                {{ audience.lastCount ?? '—' }}
                <span v-if="audience.lastCountedAt" class="block text-[12px] text-slate-400">
                  {{ sprintf(__('counted %s', textDomain), formatDate(audience.lastCountedAt, locale)) }}
                </span>
              </td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ formatDate(audience.updatedAt, locale) }}</td>
              <td class="whitespace-nowrap px-3 py-3 text-right">
                <div class="flex justify-end gap-1">
                  <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-slate-600 hover:bg-slate-100" @click="openContacts(audience)">
                    {{ __('See contacts', textDomain) }}
                  </button>
                  <template v-if="canWrite">
                    <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-primary-700 hover:bg-primary-50" @click="openEditor(audience)">
                      {{ __('Edit', textDomain) }}
                    </button>
                    <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-slate-600 hover:bg-slate-100" @click="setArchived(audience, !audience.archivedAt)">
                      {{ audience.archivedAt ? __('Restore', textDomain) : __('Archive', textDomain) }}
                    </button>
                    <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-rose-600 hover:bg-rose-50" @click="confirmTarget = audience">
                      {{ __('Erase', textDomain) }}
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="!error" class="py-16 text-center">
        <p class="text-[15px] font-medium text-slate-600">
          {{ archived ? __('No archived audiences', textDomain) : __('No audiences yet', textDomain) }}
        </p>
        <p class="mt-1 text-[13px] text-slate-400">
          {{ __('Create an audience to group contacts by their fields, tags or consent — for example, opted-in customers from one city.', textDomain) }}
        </p>
      </div>

      <div v-if="totalPages > 1" class="flex items-center justify-end gap-1 pt-2">
        <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 hover:bg-slate-100 disabled:opacity-40" :disabled="page <= 1" @click="goTo(page - 1)">‹</button>
        <span class="px-3 text-[13px] text-slate-500">{{ page }} / {{ totalPages }}</span>
        <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 hover:bg-slate-100 disabled:opacity-40" :disabled="page >= totalPages" @click="goTo(page + 1)">›</button>
      </div>
    </div>

    <AudienceEditor :audience="editor.audience" :open="editor.open" @close="editor = { open: false, audience: null }" @saved="onSaved" />

    <ConfirmActionModal
      :confirm-label="__('Erase audience', textDomain)"
      :description="__('The audience is erased; its contacts are not. A draft campaign that used it will fail its check until you pick another audience.', textDomain)"
      :loading="acting"
      :open="Boolean(confirmTarget)"
      :title="__('Erase this audience?', textDomain)"
      @cancel="confirmTarget = null"
      @confirm="erase"
    />
  </div>
</template>
