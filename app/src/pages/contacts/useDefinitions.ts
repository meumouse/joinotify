import { computed, ref } from 'vue';
import type { ContactsApiClient } from '../../services/contactsApi';
import { errorMessage } from './context';

export interface ContactField {
  id: string;
  key: string;
  label: string;
  type: string;
  options?: string[];
  position?: number;
  archivedAt?: string | null;
}

export interface ContactTag {
  id: string;
  name: string;
  description?: string | null;
  color?: string | null;
  contactCount?: number;
}

/**
 * The account's custom fields and tags, loaded once and shared by every tab
 * of the "Audiences & Contacts" screen. Writes on the Fields & tags tab call
 * `load(true)` so the other tabs see the change.
 *
 * @since 2.5.0
 * @param {ContactsApiClient} api REST client.
 * @returns {Object} The definitions store.
 */
export function createDefinitions(api: ContactsApiClient) {
  const fields = ref<ContactField[]>([]);
  const tags = ref<ContactTag[]>([]);
  const loading = ref(false);
  const loaded = ref(false);
  const error = ref('');
  let pending: Promise<void> | null = null;

  const activeFields = computed(() =>
    fields.value
      .filter((field) => !field.archivedAt)
      .slice()
      .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
  );

  const sortedTags = computed(() => tags.value.slice().sort((a, b) => a.name.localeCompare(b.name)));

  function load(refresh = false): Promise<void> {
    if (pending && !refresh) {
      return pending;
    }

    loading.value = true;
    error.value = '';

    pending = api
      .definitions(refresh)
      .then((response: any) => {
        fields.value = Array.isArray(response?.fields) ? response.fields : [];
        tags.value = Array.isArray(response?.tags) ? response.tags : [];
        loaded.value = true;
      })
      .catch((caught: unknown) => {
        error.value = errorMessage(caught);
      })
      .finally(() => {
        loading.value = false;
      });

    return pending;
  }

  function tagById(id: string): ContactTag | undefined {
    return tags.value.find((tag) => tag.id === id);
  }

  return { fields, tags, sortedTags, activeFields, loading, loaded, error, load, tagById };
}

export type Definitions = ReturnType<typeof createDefinitions>;
