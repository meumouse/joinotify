import { computed, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import type { ContactsApiClient } from '../../services/contactsApi';
import { DEFAULT_PER_PAGE, normalizePerPage, pageKeepingFirstRow, readStoredPerPage, storePerPage } from '../../utils/perPage';
import { errorMessage } from './context';

export interface ContactFilters {
  search: string;
  tag_id: string;
  opt_in_status: string;
  source: string;
  audience_id: string;
}

const EMPTY_FILTERS: ContactFilters = { search: '', tag_id: '', opt_in_status: '', source: '', audience_id: '' };

/**
 * Server-side contact listing: filters, sort, pagination and selection, backed
 * by the platform through the plugin's REST routes.
 *
 * @since 2.5.0
 * @param {ContactsApiClient} api REST client.
 * @param {Partial<ContactFilters>} [initial] Filters the list opens with (e.g. an audience).
 * @returns {Object} The listing state and its actions.
 */
export function useContactList(api: ContactsApiClient, initial: Partial<ContactFilters> = {}) {
  const items = ref<any[]>([]);
  const loading = ref(false);
  const error = ref('');
  const filters = ref<ContactFilters>({ ...EMPTY_FILTERS, ...initial });
  const sort = ref('recent');
  const pagination = ref({
    current_page: 1,
    per_page: readStoredPerPage('contacts') ?? DEFAULT_PER_PAGE,
    total_items: 0,
    total_pages: 1,
  });
  const selectedIds = ref(new Set<string>());
  let searchTimer = 0;
  let requestId = 0;

  const hasFilters = computed(() => Object.values(filters.value).some(Boolean));

  const pageSummary = computed(() => {
    const total = pagination.value.total_items;

    if (!total) {
      return __('0 results', textDomain);
    }

    const start = (pagination.value.current_page - 1) * pagination.value.per_page + 1;
    const end = Math.min(pagination.value.current_page * pagination.value.per_page, total);

    return `${start}-${end} ${__('of', textDomain)} ${total}`;
  });

  const allVisibleSelected = computed(() => items.value.length > 0 && items.value.every((item) => selectedIds.value.has(item.id)));
  const partiallyVisibleSelected = computed(() => {
    const count = items.value.filter((item) => selectedIds.value.has(item.id)).length;

    return count > 0 && count < items.value.length;
  });

  async function fetchItems() {
    // Only the newest answer lands: typing fast fires several searches.
    const current = ++requestId;

    loading.value = true;
    error.value = '';

    try {
      const response = await api.listContacts({
        ...filters.value,
        sort: sort.value,
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
      });

      if (current !== requestId) {
        return;
      }

      items.value = Array.isArray(response?.items) ? response.items : [];
      pagination.value = { ...pagination.value, ...(response?.pagination || {}) };
      selectedIds.value = new Set();
    } catch (caught) {
      if (current === requestId) {
        error.value = errorMessage(caught, __('Could not load the contacts.', textDomain));
      }
    } finally {
      if (current === requestId) {
        loading.value = false;
      }
    }
  }

  function setFilter(key: keyof ContactFilters, value: string) {
    filters.value = { ...filters.value, [key]: value || '' };
    pagination.value.current_page = 1;

    if (key === 'search') {
      window.clearTimeout(searchTimer);
      // The platform searches phones from the fourth digit on; shorter terms
      // would only return everything.
      searchTimer = window.setTimeout(fetchItems, 350);
      return;
    }

    fetchItems();
  }

  function clearFilters() {
    filters.value = { ...EMPTY_FILTERS };
    pagination.value.current_page = 1;
    fetchItems();
  }

  function setSort(value: string) {
    sort.value = value || 'recent';
    pagination.value.current_page = 1;
    fetchItems();
  }

  function goToPage(page: number) {
    const target = Math.min(Math.max(1, page), pagination.value.total_pages || 1);

    if (target !== pagination.value.current_page) {
      pagination.value.current_page = target;
      fetchItems();
    }
  }

  function setPerPage(size: number) {
    const next = normalizePerPage(size);

    pagination.value.current_page = pageKeepingFirstRow(pagination.value.current_page, pagination.value.per_page, next);
    pagination.value.per_page = next;
    storePerPage('contacts', next);
    fetchItems();
  }

  function toggleSelected(id: string, checked: boolean) {
    const next = new Set(selectedIds.value);

    if (checked) {
      next.add(id);
    } else {
      next.delete(id);
    }

    selectedIds.value = next;
  }

  function toggleSelectAll(checked: boolean) {
    selectedIds.value = checked ? new Set(items.value.map((item) => item.id)) : new Set();
  }

  function replaceItem(contact: any) {
    items.value = items.value.map((item) => (item.id === contact?.id ? { ...item, ...contact } : item));
  }

  return {
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
  };
}
