<script setup>

/**
 * BuilderVariableModal.vue frontend component.
 *
 * Create / edit dialog for custom text variables (placeholders) mapped to a
 * post type + meta key.
 *
 * @since 2.0.0
 */
import { computed, reactive, ref, watch } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import SelectField from '../../../../components/fields/SelectField.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  variable: { type: Object, default: null },
  postTypes: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  loadMetaKeys: { type: Function, default: null },
});

const emit = defineEmits(['close', 'save']);

const emptyDraft = () => ({
  id: '',
  token: '',
  label: '',
  post_type: '',
  meta_key: '',
  description: '',
  example: '',
});

const draft = reactive(emptyDraft());
const metaKeys = ref([]);
const examplePostId = ref('');
const loadingKeys = ref(false);
const metaKeysMessage = ref('');

const isEditing = computed(() => Boolean(props.variable && props.variable.id));
const postTypeField = computed(() => ({
  options: props.postTypes || [],
  placeholder: __('Select an entity', textDomain),
  searchPlaceholder: __('Search entity…', textDomain),
}));
const tokenPreview = computed(() => `{{ ${slugify(draft.token) || 'variable_name'} }}`);
const canSave = computed(() => Boolean(draft.token && draft.post_type && draft.meta_key));
const datalistId = 'joinotify-builder-meta-keys';

// reseed the form whenever the modal opens
watch(
  () => props.open,
  (open) => {
    if (!open) {
      return;
    }

    Object.assign(draft, emptyDraft(), props.variable ? {
      id: props.variable.id || '',
      token: props.variable.token || '',
      label: props.variable.label || '',
      post_type: props.variable.post_type || '',
      meta_key: props.variable.meta_key || '',
      description: props.variable.description || '',
      example: props.variable.example || '',
    } : {});

    metaKeys.value = [];
    examplePostId.value = '';
    metaKeysMessage.value = '';
  },
  { immediate: true }
);

function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9_]+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '');
}

async function onLoadKeys() {
  if (typeof props.loadMetaKeys !== 'function' || !draft.post_type) {
    return;
  }

  loadingKeys.value = true;
  metaKeysMessage.value = '';

  try {
    const response = await props.loadMetaKeys({ post_type: draft.post_type, post_id: examplePostId.value });
    metaKeys.value = Array.isArray(response?.keys) ? response.keys : [];

    if (response?.post_id) {
      examplePostId.value = String(response.post_id);
    }

    if (!metaKeys.value.length) {
      metaKeysMessage.value = response?.empty_message || __('No meta keys were found for this entity.', textDomain);
    }
  } catch (error) {
    metaKeys.value = [];
    metaKeysMessage.value = error instanceof Error ? error.message : String(error);
  } finally {
    loadingKeys.value = false;
  }
}

function pickKey(entry) {
  draft.meta_key = entry.key;

  if (entry.sample && !draft.example) {
    draft.example = entry.sample;
  }
}

function onSave() {
  if (!canSave.value || props.saving) {
    return;
  }

  emit('save', { ...draft, token: slugify(draft.token) });
}
</script>

<template>
  <ModalDialog
    :open="open"
    :title="isEditing ? __('Edit variable', textDomain) : __('New variable', textDomain)"
    :description="__('Map an entity and meta key to a named variable available in the builder.', textDomain)"
    size-class="max-w-3xl"
    @close="$emit('close')"
  >
    <div class="mb-4 flex items-center justify-end">
      <code class="rounded bg-primary-50 px-2 py-1 text-[12px] font-semibold text-primary-700 ring-1 ring-primary-100">{{ tokenPreview }}</code>
    </div>

    <div class="grid items-start gap-5 md:grid-cols-2">
      <label class="block">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Variable name', textDomain) }}</span>
        <input
          v-model="draft.token"
          type="text"
          :placeholder="'product_sku'"
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Label (optional)', textDomain) }}</span>
        <input
          v-model="draft.label"
          type="text"
          :placeholder="__('Product SKU', textDomain)"
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Entity (post type)', textDomain) }}</span>
        <SelectField
          :field="postTypeField"
          name="post_type"
          :model-value="draft.post_type"
          @update:model-value="draft.post_type = $event"
        />
      </label>

      <div class="block">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Example post ID (optional)', textDomain) }}</span>
        <div class="flex gap-2">
          <input
            v-model="examplePostId"
            type="text"
            inputmode="numeric"
            :placeholder="__('latest post', textDomain)"
            class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
          />
          <button
            type="button"
            class="shrink-0 rounded-[8px] border border-primary-200 bg-white px-4 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!draft.post_type || loadingKeys"
            @click="onLoadKeys"
          >
            {{ loadingKeys ? __('Loading…', textDomain) : __('Load keys', textDomain) }}
          </button>
        </div>
      </div>

      <label class="block md:col-span-2">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Meta key', textDomain) }}</span>
        <input
          v-model="draft.meta_key"
          type="text"
          :list="datalistId"
          :placeholder="'_sku'"
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
        />
        <datalist :id="datalistId">
          <option v-for="entry in metaKeys" :key="entry.key" :value="entry.key" />
        </datalist>
      </label>
    </div>

    <!-- Discovered meta keys -->
    <div v-if="metaKeysMessage" class="mt-4 text-[13px] text-slate-500">{{ metaKeysMessage }}</div>

    <div v-if="metaKeys.length" class="mt-4">
      <span class="mb-2 block text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Example keys', textDomain) }}</span>
      <div class="flex max-h-40 flex-wrap gap-2 overflow-y-auto">
        <button
          v-for="entry in metaKeys"
          :key="entry.key"
          type="button"
          class="group inline-flex max-w-full items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-left text-[12px] text-slate-600 transition hover:border-primary-300 hover:bg-primary-50"
          @click="pickKey(entry)"
        >
          <span class="font-mono font-medium text-slate-700">{{ entry.key }}</span>
          <span v-if="entry.sample" class="truncate text-slate-400">{{ entry.sample }}</span>
        </button>
      </div>
    </div>

    <div class="mt-5 grid gap-5 md:grid-cols-2">
      <label class="block">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Description (optional)', textDomain) }}</span>
        <input
          v-model="draft.description"
          type="text"
          :placeholder="__('Shown in the variable picker', textDomain)"
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-[13px] font-medium text-slate-700">{{ __('Example value (optional)', textDomain) }}</span>
        <input
          v-model="draft.example"
          type="text"
          :placeholder="__('Sample value shown in the builder', textDomain)"
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
        />
      </label>
    </div>

    <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
      <button
        type="button"
        class="rounded-[8px] border border-slate-200 px-6 py-3 text-[14px] font-medium text-slate-600 transition hover:bg-slate-50"
        @click="$emit('close')"
      >
        {{ __('Cancel', textDomain) }}
      </button>
      <button
        type="button"
        class="rounded-[8px] bg-primary-600 px-6 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="saving || !canSave"
        @click="onSave"
      >
        {{ saving ? __('Saving…', textDomain) : (isEditing ? __('Update variable', textDomain) : __('Add variable', textDomain)) }}
      </button>
    </div>
  </ModalDialog>
</template>
