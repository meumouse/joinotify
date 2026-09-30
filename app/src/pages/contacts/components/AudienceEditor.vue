<script setup>
/**
 * AudienceEditor.vue — creates an audience or edits one: its name, a note and
 * the filter over the contact base, with a live count (by consent) and a
 * sample of who it selects while the filter is typed.
 *
 * @since 2.5.0
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { __, sprintf, textDomain } from '../../../utils/i18n';
import { useContactsContext, errorMessage } from '../context';
import { MAX_RULES, completeRules, countRules, emptyGroup, newRule } from '../audienceFilter';
import { formatPhone } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';
import FilterGroupEditor from './FilterGroupEditor.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  audience: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const { api, definitions, toast } = useContactsContext();

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const name = ref('');
const description = ref('');
const filter = ref(emptyGroup());
const schema = ref({});
const saving = ref(false);
const error = ref('');
const preview = ref(null);
const previewing = ref(false);
const previewError = ref('');
let previewTimer = 0;
let previewRequest = 0;

const isEdit = computed(() => Boolean(props.audience?.id));
const ruleCount = computed(() => countRules(filter.value));
const canAdd = computed(() => ruleCount.value < MAX_RULES);

async function loadSchema() {
  try {
    const response = await api.audienceSchema();

    schema.value = response.schema || {};
  } catch (caught) {
    // The builder still works with its fallback operators; the preview says if one is refused.
    schema.value = {};
  }
}

watch(
  () => props.open,
  async (open) => {
    if (!open) {
      return;
    }

    error.value = '';
    preview.value = null;
    name.value = props.audience?.name || '';
    description.value = props.audience?.description || '';
    filter.value = props.audience?.filter ? JSON.parse(JSON.stringify(props.audience.filter)) : emptyGroup();

    definitions.load();
    await loadSchema();

    if (!filter.value.rules.length) {
      filter.value.rules.push(newRule('consent', schema.value));
    }
  },
  { immediate: true }
);

async function runPreview() {
  const complete = completeRules(filter.value);
  const current = ++previewRequest;

  if (!complete.rules.length) {
    preview.value = null;
    return;
  }

  previewing.value = true;
  previewError.value = '';

  try {
    const response = await api.previewAudience(complete);

    if (current === previewRequest) {
      preview.value = response.preview || null;
    }
  } catch (caught) {
    if (current === previewRequest) {
      previewError.value = errorMessage(caught);
    }
  } finally {
    if (current === previewRequest) {
      previewing.value = false;
    }
  }
}

// Recount a moment after the typing stops: each preview is a request to the platform.
watch(
  filter,
  () => {
    if (!props.open) {
      return;
    }

    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(runPreview, 800);
  },
  { deep: true }
);

onBeforeUnmount(() => window.clearTimeout(previewTimer));

const stats = computed(() => {
  const data = preview.value || {};

  return [
    { label: __('Selected', textDomain), value: data.total ?? 0 },
    { label: __('Reachable', textDomain), value: data.reachable ?? 0, hint: __('Opted in and not suppressed', textDomain) },
    { label: __('Opted in', textDomain), value: data.optedIn ?? 0 },
    { label: __('Unknown consent', textDomain), value: data.unknown ?? 0 },
    { label: __('Opted out', textDomain), value: data.optedOut ?? 0 },
    { label: __('Suppressed', textDomain), value: data.suppressed ?? 0 },
  ];
});

async function save() {
  error.value = '';

  const complete = completeRules(filter.value);

  if (!name.value.trim()) {
    error.value = __('Give the audience a name.', textDomain);
    return;
  }

  if (!complete.rules.length) {
    error.value = __('Add at least one complete condition.', textDomain);
    return;
  }

  saving.value = true;

  try {
    const response = await api.saveAudience(props.audience?.id || '', {
      name: name.value,
      description: description.value,
      filter: complete,
    });

    toast(response.message, 'success', __('Audiences', textDomain));
    emit('saved', response.audience);
  } catch (caught) {
    error.value = errorMessage(caught);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog
        :close-on-backdrop="!saving"
        :open="open"
        size-class="max-w-6xl"
        :title="isEdit ? __('Edit audience', textDomain) : __('New audience', textDomain)"
        :description="__('An audience is a saved filter over your contacts. It is recounted every time it is used, so new contacts that match join it on their own.', textDomain)"
        @close="$emit('close')"
      >
        <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
          <div class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="flex flex-col gap-1">
                <span class="text-[12px] font-medium text-slate-500">{{ __('Name', textDomain) }}</span>
                <input v-model="name" :class="inputClass" maxlength="80" type="text" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="text-[12px] font-medium text-slate-500">{{ __('Note (optional)', textDomain) }}</span>
                <input v-model="description" :class="inputClass" maxlength="300" type="text" />
              </label>
            </div>

            <FilterGroupEditor
              :can-add="canAdd"
              :fields="definitions.activeFields.value"
              :group="filter"
              :schema="schema"
              :tags="definitions.sortedTags.value"
            />

            <p class="text-[12px] text-slate-400">
              {{ sprintf(__('%1$d of %2$d conditions, up to 3 levels of groups.', textDomain), ruleCount, MAX_RULES) }}
            </p>

            <p v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{{ error }}</p>
          </div>

          <aside class="flex flex-col gap-3 rounded-[8px] bg-slate-50 p-4">
            <div class="flex items-center justify-between">
              <h4 class="text-[13px] font-semibold text-slate-600">{{ __('Preview', textDomain) }}</h4>
              <span v-if="previewing" class="text-[12px] text-slate-400">{{ __('Counting…', textDomain) }}</span>
            </div>

            <p v-if="previewError" class="text-[12px] text-danger">{{ previewError }}</p>

            <dl class="grid grid-cols-2 gap-2">
              <div v-for="item in stats" :key="item.label" class="rounded-[8px] bg-white p-2 ring-1 ring-slate-100" :title="item.hint || ''">
                <dt class="text-[11px] uppercase tracking-wide text-slate-400">{{ item.label }}</dt>
                <dd class="text-[18px] font-semibold text-ink">{{ item.value }}</dd>
              </div>
            </dl>

            <div v-if="preview && preview.sample && preview.sample.length">
              <p class="mb-1 text-[12px] font-medium text-slate-500">{{ __('Some of them', textDomain) }}</p>
              <ul class="space-y-1 text-[13px] text-slate-600">
                <li v-for="contact in preview.sample" :key="contact.id" class="truncate">
                  {{ contact.name || contact.firstName || formatPhone(contact.phone) }}
                  <span class="text-slate-400">{{ formatPhone(contact.phone) }}</span>
                </li>
              </ul>
            </div>
          </aside>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <BaseButton :disabled="saving" :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('close')" />
          <BaseButton :loading="saving" :title="isEdit ? __('Save changes', textDomain) : __('Create audience', textDomain)" @click="save" />
        </div>
      </ModalDialog>
    </div>
  </Teleport>
</template>
