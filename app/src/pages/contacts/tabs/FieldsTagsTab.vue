<script setup>
/**
 * FieldsTagsTab.vue — the custom fields and tags that organize the contact
 * base: create, edit, archive and erase fields; create, edit and erase tags.
 *
 * @since 2.5.0
 */
import { computed, onMounted, ref } from 'vue';
import { __, _n, sprintf, textDomain } from '../../../utils/i18n';
import { useContactsContext } from '../context';
import { fieldTypeOptions, labelOf } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseCheckbox from '../../../components/buttons/checkbox/BaseCheckbox.vue';
import ConfirmActionModal from '../../../components/workflows/ConfirmActionModal.vue';
import FieldFormModal from '../components/FieldFormModal.vue';
import TagChip from '../components/TagChip.vue';
import TagFormModal from '../components/TagFormModal.vue';

const { api, canWrite, definitions, toast, notifyError, navigate } = useContactsContext();

const fields = ref([]);
const loading = ref(false);
const showArchived = ref(false);
const fieldModal = ref({ open: false, field: null });
const tagModal = ref({ open: false, tag: null });
const confirm = ref({ kind: '', target: null });
const acting = ref(false);

const visibleFields = computed(() =>
  fields.value
    .filter((field) => showArchived.value || !field.archivedAt)
    .slice()
    .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
);
const archivedCount = computed(() => fields.value.filter((field) => field.archivedAt).length);

async function loadFields(refresh = false) {
  loading.value = true;

  try {
    const response = await api.definitions(refresh, true);

    fields.value = response.fields || [];
  } catch (caught) {
    notifyError(caught);
  } finally {
    loading.value = false;
  }
}

async function refreshAll() {
  await Promise.all([loadFields(true), definitions.load(true)]);
}

async function onFieldSaved() {
  fieldModal.value = { open: false, field: null };
  await refreshAll();
}

async function onTagSaved() {
  tagModal.value = { open: false, tag: null };
  await definitions.load(true);
}

async function toggleArchive(field) {
  try {
    const response = await api.saveField(field.id, { type: field.type, archived: !field.archivedAt });

    toast(
      field.archivedAt ? __('Custom field restored.', textDomain) : __('Custom field archived. It keeps its values but accepts no new ones.', textDomain),
      'success',
      __('Custom fields', textDomain)
    );
    await refreshAll();
  } catch (caught) {
    notifyError(caught);
  }
}

const confirmCopy = computed(() => {
  if (confirm.value.kind === 'field') {
    return {
      title: __('Erase this custom field?', textDomain),
      description: sprintf(
        /* translators: %s: field label */
        __('"%s" and its value on every contact are erased. Audiences that filter by it stop matching. This cannot be undone; archive the field instead to keep its values.', textDomain),
        confirm.value.target?.label || ''
      ),
      label: __('Erase field', textDomain),
    };
  }

  return {
    title: __('Erase this tag?', textDomain),
    description: sprintf(
      /* translators: %s: tag name */
      __('"%s" is taken off every contact and erased. This cannot be undone.', textDomain),
      confirm.value.target?.name || ''
    ),
    label: __('Erase tag', textDomain),
  };
});

async function runConfirm() {
  acting.value = true;

  try {
    const { kind, target } = confirm.value;
    const response = kind === 'field' ? await api.deleteField(target.id) : await api.deleteTag(target.id);

    toast(response.message, 'success', kind === 'field' ? __('Custom fields', textDomain) : __('Tags', textDomain));
    confirm.value = { kind: '', target: null };
    await (kind === 'field' ? refreshAll() : definitions.load(true));
  } catch (caught) {
    notifyError(caught);
  } finally {
    acting.value = false;
  }
}

onMounted(() => {
  loadFields();
  definitions.load();
});
</script>

<template>
  <div class="mt-6 grid gap-6 xl:grid-cols-2">
    <!-- Custom fields -->
    <section class="rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
      <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-[16px] font-semibold text-ink">{{ __('Custom fields', textDomain) }}</h2>
            <p class="mt-1 text-[13px] text-slate-500">{{ __('The data your contacts carry beyond name, phone and e-mail: city, plan, birthday…', textDomain) }}</p>
          </div>
          <BaseButton v-if="canWrite" size="sm" :title="__('New field', textDomain)" @click="fieldModal = { open: true, field: null }" />
        </div>

        <BaseCheckbox
          v-if="archivedCount"
          v-model="showArchived"
          :label="sprintf(__('Show archived fields (%d)', textDomain), archivedCount)"
        />

        <div v-if="loading && !fields.length" class="py-10 text-center text-[14px] text-slate-400">{{ __('Loading…', textDomain) }}</div>

        <div v-else-if="visibleFields.length" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-left">
            <thead>
              <tr class="text-[12px] uppercase tracking-wide text-slate-400">
                <th class="px-3 py-2 font-medium">{{ __('Field', textDomain) }}</th>
                <th class="px-3 py-2 font-medium">{{ __('Type', textDomain) }}</th>
                <th v-if="canWrite" class="px-3 py-2 text-right font-medium">{{ __('Actions', textDomain) }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-[14px] text-slate-700">
              <tr v-for="field in visibleFields" :key="field.id" :class="field.archivedAt ? 'opacity-60' : ''">
                <td class="px-3 py-2">
                  <span class="block font-medium">{{ field.label }}</span>
                  <code class="text-[12px] text-slate-400">{{ field.key }}</code>
                  <span v-if="field.archivedAt" class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500">{{ __('Archived', textDomain) }}</span>
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-slate-500">
                  {{ labelOf(fieldTypeOptions(), field.type) }}
                  <span v-if="field.options && field.options.length" class="block text-[12px] text-slate-400">
                    {{ sprintf(_n('%d option', '%d options', field.options.length, textDomain), field.options.length) }}
                  </span>
                </td>
                <td v-if="canWrite" class="whitespace-nowrap px-3 py-2 text-right">
                  <div class="flex justify-end gap-1">
                    <button v-if="!field.archivedAt" type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-primary-700 hover:bg-primary-50" @click="fieldModal = { open: true, field }">
                      {{ __('Edit', textDomain) }}
                    </button>
                    <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-slate-600 hover:bg-slate-100" @click="toggleArchive(field)">
                      {{ field.archivedAt ? __('Restore', textDomain) : __('Archive', textDomain) }}
                    </button>
                    <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-rose-600 hover:bg-rose-50" @click="confirm = { kind: 'field', target: field }">
                      {{ __('Erase', textDomain) }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-else class="py-10 text-center text-[13px] text-slate-400">{{ __('No custom fields yet.', textDomain) }}</p>
      </div>
    </section>

    <!-- Tags -->
    <section class="rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
      <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-[16px] font-semibold text-ink">{{ __('Tags', textDomain) }}</h2>
            <p class="mt-1 text-[13px] text-slate-500">{{ __('Labels that group contacts: customers, leads of a form, VIPs… Integrations create the tags they use on their own.', textDomain) }}</p>
          </div>
          <BaseButton v-if="canWrite" size="sm" :title="__('New tag', textDomain)" @click="tagModal = { open: true, tag: null }" />
        </div>

        <div v-if="definitions.loading.value && !definitions.tags.value.length" class="py-10 text-center text-[14px] text-slate-400">{{ __('Loading…', textDomain) }}</div>

        <ul v-else-if="definitions.sortedTags.value.length" class="divide-y divide-slate-50">
          <li v-for="tag in definitions.sortedTags.value" :key="tag.id" class="flex flex-wrap items-center justify-between gap-3 py-2">
            <div class="min-w-0">
              <TagChip :color="tag.color || ''" :name="tag.name" />
              <span v-if="tag.description" class="ml-2 text-[12px] text-slate-400">{{ tag.description }}</span>
            </div>
            <div class="flex items-center gap-1">
              <button
                type="button"
                class="rounded-[8px] px-2 py-1 text-[13px] text-slate-600 hover:bg-slate-100"
                :title="__('See the contacts with this tag', textDomain)"
                @click="navigate('contacts', { tag_id: tag.id })"
              >
                {{ sprintf(_n('%d contact', '%d contacts', tag.contactCount || 0, textDomain), tag.contactCount || 0) }}
              </button>
              <template v-if="canWrite">
                <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-primary-700 hover:bg-primary-50" @click="tagModal = { open: true, tag }">
                  {{ __('Edit', textDomain) }}
                </button>
                <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-rose-600 hover:bg-rose-50" @click="confirm = { kind: 'tag', target: tag }">
                  {{ __('Erase', textDomain) }}
                </button>
              </template>
            </div>
          </li>
        </ul>

        <p v-else class="py-10 text-center text-[13px] text-slate-400">{{ __('No tags yet.', textDomain) }}</p>
      </div>
    </section>

    <FieldFormModal :field="fieldModal.field" :open="fieldModal.open" @close="fieldModal = { open: false, field: null }" @saved="onFieldSaved" />
    <TagFormModal :open="tagModal.open" :tag="tagModal.tag" @close="tagModal = { open: false, tag: null }" @saved="onTagSaved" />

    <ConfirmActionModal
      :confirm-label="confirmCopy.label"
      :description="confirmCopy.description"
      :loading="acting"
      :open="Boolean(confirm.kind)"
      :title="confirmCopy.title"
      @cancel="confirm = { kind: '', target: null }"
      @confirm="runConfirm"
    />
  </div>
</template>
