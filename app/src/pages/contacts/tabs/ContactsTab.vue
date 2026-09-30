<script setup>
/**
 * ContactsTab.vue — the contact base: search, filters, sort and pagination
 * over the platform's list, a drawer with each contact and the dialog that
 * adds or edits one.
 *
 * @since 2.5.0
 */
import { computed, onMounted, ref } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { downloadText } from '../../../utils/downloadText';
import { useContactsContext } from '../context';
import { useContactList } from '../useContactList';
import {
  consentBadgeClass,
  consentOptions,
  formatDate,
  formatPhone,
  labelOf,
  sourceOptions,
} from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import PerPageSelect from '../../../components/workflows/PerPageSelect.vue';
import ContactDrawer from '../components/ContactDrawer.vue';
import ContactFormModal from '../components/ContactFormModal.vue';
import BulkTagModal from '../components/BulkTagModal.vue';
import ImportModal from '../components/ImportModal.vue';
import BaseCheckbox from '../../../components/buttons/checkbox/BaseCheckbox.vue';
import TagChip from '../components/TagChip.vue';

const { api, bootstrap, canWrite, definitions, notifyError, route, navigate } = useContactsContext();

const list = useContactList(api, {
  audience_id: route.value.params.audience_id || '',
  tag_id: route.value.params.tag_id || '',
});
const {
  items,
  loading,
  error,
  filters,
  sort,
  pagination,
  selectedIds,
  hasFilters,
  pageSummary,
  allVisibleSelected,
  partiallyVisibleSelected,
  fetchItems,
  setFilter,
  clearFilters,
  setSort,
  goToPage,
  setPerPage,
  toggleSelected,
  toggleSelectAll,
  replaceItem,
} = list;

const bulkAction = ref('');
const importOpen = ref(false);

const searchTerm = ref('');
const openContactId = ref(route.value.params.id || '');
const formOpen = ref(false);
const formContact = ref(null);
const exporting = ref(false);
const drawer = ref(null);

const locale = computed(() => bootstrap.locale || '');
const audienceName = computed(() => route.value.params.audience_name || '');

const tagFilterOptions = computed(() => [
  { label: __('All tags', textDomain), value: '' },
  ...definitions.sortedTags.value.map((tag) => ({ label: tag.name, value: tag.id })),
]);
const consentFilterOptions = computed(() => [{ label: __('Any consent', textDomain), value: '' }, ...consentOptions()]);
const sourceFilterOptions = computed(() => [{ label: __('Any source', textDomain), value: '' }, ...sourceOptions()]);
const sortOptions = computed(() => [
  { label: __('Newest first', textDomain), value: 'recent' },
  { label: __('Name', textDomain), value: 'name' },
  { label: __('Last message received', textDomain), value: 'last_inbound' },
]);

function contactName(contact) {
  return contact.name || [contact.firstName, contact.lastName].filter(Boolean).join(' ') || contact.profileName || '';
}

function lastInteraction(contact) {
  const dates = [contact.lastInboundAt, contact.lastOutboundAt].filter(Boolean).sort();

  return dates.length ? dates[dates.length - 1] : '';
}

function applySearch(event) {
  searchTerm.value = event.target.value;
  setFilter('search', searchTerm.value);
}

function resetFilters() {
  searchTerm.value = '';
  clearAudience();
  clearFilters();
}

function clearAudience() {
  if (filters.value.audience_id) {
    navigate('contacts');
    setFilter('audience_id', '');
  }
}

function openContact(id) {
  formOpen.value = false;
  openContactId.value = id;
}

function openCreate() {
  formContact.value = null;
  formOpen.value = true;
}

function openEdit(contact) {
  formContact.value = contact;
  formOpen.value = true;
}

function onSaved(contact) {
  formOpen.value = false;

  if (formContact.value?.id) {
    replaceItem(contact);
    drawer.value?.reload();
  } else {
    fetchItems();
  }
}

function onDeleted() {
  openContactId.value = '';
  fetchItems();
}

async function exportCsv() {
  exporting.value = true;

  try {
    const response = await api.exportContacts({ ...filters.value });

    downloadText(response.content, response.filename);
  } catch (caught) {
    notifyError(caught);
  } finally {
    exporting.value = false;
  }
}

onMounted(() => {
  definitions.load();
  fetchItems();
});
</script>

<template>
  <div class="mt-6 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
    <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6 lg:px-8 lg:py-6">
      <!-- Filters -->
      <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
        <div class="flex flex-1 flex-col lg:min-w-[260px]">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Search by name, e-mail or phone', textDomain) }}</label>
          <input
            :value="searchTerm"
            type="search"
            :placeholder="__('Type to filter…', textDomain)"
            class="rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none"
            @input="applySearch"
          />
        </div>
        <div class="flex w-full flex-col sm:w-48">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Tag', textDomain) }}</label>
          <BaseListboxSelect :model-value="filters.tag_id" :options="tagFilterOptions" @update:model-value="setFilter('tag_id', $event)" />
        </div>
        <div class="flex w-full flex-col sm:w-44">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Consent', textDomain) }}</label>
          <BaseListboxSelect :model-value="filters.opt_in_status" :options="consentFilterOptions" @update:model-value="setFilter('opt_in_status', $event)" />
        </div>
        <div class="flex w-full flex-col sm:w-44">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Source', textDomain) }}</label>
          <BaseListboxSelect :model-value="filters.source" :options="sourceFilterOptions" @update:model-value="setFilter('source', $event)" />
        </div>
        <div class="flex w-full flex-col sm:w-52">
          <label class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Sort by', textDomain) }}</label>
          <BaseListboxSelect :model-value="sort" :options="sortOptions" @update:model-value="setSort" />
        </div>
        <button
          v-if="hasFilters || searchTerm"
          type="button"
          class="rounded-[8px] border border-slate-200 bg-white px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50"
          @click="resetFilters"
        >
          {{ __('Clear filters', textDomain) }}
        </button>
      </div>

      <div v-if="filters.audience_id" class="flex items-center gap-2 text-[13px] text-slate-600">
        <span>{{ __('Showing the contacts of the audience', textDomain) }}</span>
        <span class="rounded-full bg-primary-50 px-3 py-0.5 font-medium text-primary-800">{{ audienceName || filters.audience_id }}</span>
        <button type="button" class="text-primary-700 hover:underline" @click="clearAudience">{{ __('Show all', textDomain) }}</button>
      </div>

      <!-- Error -->
      <div v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p>{{ error }}</p>
          <BaseButton :title="__('Try again', textDomain)" variant="secondary" @click="fetchItems" />
        </div>
      </div>

      <!-- Toolbar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-3">
          <BaseButton v-if="canWrite" :title="__('Add contact', textDomain)" @click="openCreate" />
          <BaseButton v-if="canWrite" :title="__('Import CSV', textDomain)" variant="secondary" @click="importOpen = true" />
          <button
            type="button"
            class="rounded-[8px] border border-slate-200 px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="loading || exporting || !pagination.total_items || !canWrite"
            :title="__('Download the contacts matching the filters as a CSV file', textDomain)"
            @click="exportCsv"
          >
            {{ exporting ? __('Exporting…', textDomain) : __('Export CSV', textDomain) }}
          </button>
          <template v-if="canWrite">
            <button
              type="button"
              class="rounded-[8px] border border-slate-200 px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading || !pagination.total_items"
              @click="bulkAction = 'add'"
            >
              {{ selectedIds.size ? `${__('Add tag', textDomain)} (${selectedIds.size})` : __('Add tag', textDomain) }}
            </button>
            <button
              type="button"
              class="rounded-[8px] border border-slate-200 px-4 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading || !pagination.total_items"
              @click="bulkAction = 'remove'"
            >
              {{ selectedIds.size ? `${__('Remove tag', textDomain)} (${selectedIds.size})` : __('Remove tag', textDomain) }}
            </button>
          </template>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-[8px] border border-slate-200 px-3 py-2 text-[13px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
            :disabled="loading"
            @click="fetchItems"
          >
            {{ __('Refresh', textDomain) }}
          </button>
          <span class="text-[13px] text-slate-500">{{ pageSummary }}</span>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading && !items.length" class="py-16 text-center text-[14px] text-slate-400">{{ __('Loading…', textDomain) }}</div>

      <!-- Table -->
      <div v-else-if="items.length" class="overflow-x-auto" :class="loading ? 'opacity-60' : ''">
        <table class="min-w-full divide-y divide-slate-100 text-left">
          <thead>
            <tr class="text-[12px] uppercase tracking-wide text-slate-400">
              <th v-if="canWrite" class="px-3 py-3">
                <BaseCheckbox
                  :aria-label="__('Select all visible contacts', textDomain)"
                  :indeterminate="partiallyVisibleSelected"
                  :model-value="allVisibleSelected"
                  @change="toggleSelectAll($event)"
                />
              </th>
              <th class="px-3 py-3 font-medium">{{ __('Contact', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Phone', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Tags', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Consent', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Source', textDomain) }}</th>
              <th class="px-3 py-3 font-medium">{{ __('Last interaction', textDomain) }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 text-[14px] text-slate-700">
            <tr
              v-for="contact in items"
              :key="contact.id"
              class="cursor-pointer transition hover:bg-slate-50"
              tabindex="0"
              @click="openContact(contact.id)"
              @keydown.enter="openContact(contact.id)"
            >
              <td v-if="canWrite" class="px-3 py-3" @click.stop>
                <BaseCheckbox
                  :aria-label="`${__('Select', textDomain)} ${contactName(contact) || formatPhone(contact.phone)}`"
                  :model-value="selectedIds.has(contact.id)"
                  @change="toggleSelected(contact.id, $event)"
                />
              </td>
              <td class="px-3 py-3">
                <span class="block font-medium text-slate-800">{{ contactName(contact) || '—' }}</span>
                <span v-if="contact.email" class="block text-[12px] text-slate-400">{{ contact.email }}</span>
              </td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ formatPhone(contact.phone) }}</td>
              <td class="px-3 py-3">
                <div class="flex max-w-[260px] flex-wrap gap-1">
                  <TagChip v-for="tag in (contact.tags || []).slice(0, 3)" :key="tag.id" :color="tag.color || ''" :name="tag.name" />
                  <span v-if="(contact.tags || []).length > 3" class="text-[12px] text-slate-400">+{{ contact.tags.length - 3 }}</span>
                </div>
              </td>
              <td class="whitespace-nowrap px-3 py-3">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-medium ring-1 ring-inset" :class="consentBadgeClass(contact.optInStatus)">
                  {{ labelOf(consentOptions(), contact.optInStatus) }}
                </span>
              </td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ labelOf(sourceOptions(), contact.source) }}</td>
              <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ formatDate(lastInteraction(contact), locale) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty -->
      <div v-else-if="!error" class="py-16 text-center">
        <p class="text-[15px] font-medium text-slate-600">
          {{ hasFilters ? __('No contact matches these filters', textDomain) : __('No contacts yet', textDomain) }}
        </p>
        <p class="mt-1 text-[13px] text-slate-400">
          {{ hasFilters
            ? __('Change or clear the filters to see more contacts.', textDomain)
            : __('Add a contact by hand, or turn on the sync of your integrations to bring your customers and leads.', textDomain) }}
        </p>
      </div>

      <!-- Pagination -->
      <div v-if="items.length" class="flex flex-wrap items-center justify-between gap-3 pt-2">
        <span class="text-[13px] text-slate-500">{{ pageSummary }}</span>
        <div class="flex flex-wrap items-center gap-1">
          <PerPageSelect class="mr-3" :disabled="loading" :model-value="pagination.per_page" @update:model-value="setPerPage" />
          <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="goToPage(1)">«</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page <= 1" @click="goToPage(pagination.current_page - 1)">‹</button>
          <span class="px-3 text-[13px] text-slate-500">{{ pagination.current_page }} / {{ pagination.total_pages }}</span>
          <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page >= pagination.total_pages" @click="goToPage(pagination.current_page + 1)">›</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-[13px] text-slate-600 transition hover:bg-slate-100 disabled:opacity-40" :disabled="pagination.current_page >= pagination.total_pages" @click="goToPage(pagination.total_pages)">»</button>
        </div>
      </div>
    </div>

    <ContactDrawer
      ref="drawer"
      :contact-id="openContactId"
      @changed="replaceItem"
      @close="openContactId = ''"
      @deleted="onDeleted"
      @merged="fetchItems"
      @edit="openEdit"
    />

    <ImportModal :open="importOpen" @close="importOpen = false" @imported="fetchItems(); definitions.load(true)" />

    <BulkTagModal
      :action="bulkAction || 'add'"
      :contact-ids="[...selectedIds]"
      :filters="filters"
      :open="Boolean(bulkAction)"
      :total="pagination.total_items"
      @applied="bulkAction = ''; fetchItems()"
      @close="bulkAction = ''"
    />

    <ContactFormModal
      :contact="formContact"
      :open="formOpen"
      @close="formOpen = false"
      @open-contact="openContact"
      @saved="onSaved"
    />
  </div>
</template>
