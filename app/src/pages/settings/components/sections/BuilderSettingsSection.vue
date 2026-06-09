<script setup>

/**
 * BuilderSettingsSection.vue frontend component.
 *
 * Lets the user register custom text variables (placeholders) mapped to a
 * post type + meta key, made available in the flow builder.
 *
 * @since 2.0.0
 */
import { computed, reactive, ref } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import SelectField from '../../../../components/fields/SelectField.vue';

const props = defineProps({
  items: { type: Array, default: () => [] },
  postTypes: { type: Array, default: () => [] },
  saveVariable: { type: Function, default: null },
  deleteVariable: { type: Function, default: null },
  loadMetaKeys: { type: Function, default: null },
});

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
const isEditing = ref(false);
const saving = ref(false);
const metaKeys = ref([]);
const examplePostId = ref('');
const loadingKeys = ref(false);
const metaKeysMessage = ref('');

const postTypeField = computed(() => ({
  options: props.postTypes || [],
  placeholder: __('Select an entity', textDomain),
  searchable: true,
  searchPlaceholder: __('Search entity…', textDomain),
}));

const tokenPreview = computed(() => `{{ ${slugify(draft.token) || 'variable_name'} }}`);
const datalistId = 'joinotify-builder-meta-keys';

function displayToken(token) {
  return '{{ ' + (token || '') + ' }}';
}

function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9_]+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '');
}

function postTypeLabel(slug) {
  const match = (props.postTypes || []).find((option) => option.value === slug);
  return match ? match.label : slug;
}

function resetDraft() {
  Object.assign(draft, emptyDraft());
  isEditing.value = false;
  metaKeys.value = [];
  examplePostId.value = '';
  metaKeysMessage.value = '';
}

function startEdit(item) {
  Object.assign(draft, {
    id: item.id || '',
    token: item.token || '',
    label: item.label || '',
    post_type: item.post_type || '',
    meta_key: item.meta_key || '',
    description: item.description || '',
    example: item.example || '',
  });
  isEditing.value = true;
  metaKeys.value = [];
  examplePostId.value = '';
  metaKeysMessage.value = '';

  if (typeof window !== 'undefined') {
    window.requestAnimationFrame(() => {
      document.getElementById('joinotify-builder-variable-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
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

async function onSave() {
  if (typeof props.saveVariable !== 'function') {
    return;
  }

  saving.value = true;

  try {
    const ok = await props.saveVariable({
      ...draft,
      token: slugify(draft.token),
    });

    if (ok) {
      resetDraft();
    }
  } finally {
    saving.value = false;
  }
}

function onDelete(item) {
  if (typeof props.deleteVariable === 'function') {
    props.deleteVariable(item);
  }
}
</script>

<template>
  <div class="space-y-8">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Custom variables', textDomain) }}</h3>
      <p class="mt-1 max-w-2xl text-[13px] leading-5 text-slate-500">
        {{ __('Map an entity (post type) and a meta key to a named variable. The variable becomes available in the flow builder and is replaced at runtime with the matching post meta value.', textDomain) }}
      </p>
    </div>

    <!-- Existing variables -->
    <div v-if="!items.length" class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-5 text-[14px] text-slate-500">
      {{ __('No custom variable yet. Create your first one below.', textDomain) }}
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="item in items"
        :key="item.id"
        class="flex flex-wrap items-center gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4"
      >
        <div class="min-w-[220px] flex-1">
          <code class="rounded bg-primary-50 px-2 py-1 text-[13px] font-semibold text-primary-700">{{ displayToken(item.token) }}</code>
          <div v-if="item.label" class="mt-1 text-[13px] font-medium text-slate-700">{{ item.label }}</div>
          <div class="mt-1 text-[12px] text-slate-400">
            {{ postTypeLabel(item.post_type) }} &middot; <span class="font-mono">{{ item.meta_key }}</span>
          </div>
        </div>

        <button
          type="button"
          class="rounded-[8px] border border-primary-200 px-4 py-2 text-[14px] font-medium text-primary-700 transition hover:bg-primary-50"
          @click="startEdit(item)"
        >
          {{ __('Edit', textDomain) }}
        </button>

        <button
          type="button"
          class="rounded-[8px] border border-rose-200 px-4 py-2 text-[14px] font-medium text-rose-400 transition hover:bg-rose-50"
          @click="onDelete(item)"
        >
          {{ __('Remove', textDomain) }}
        </button>
      </div>
    </div>

    <!-- Create / edit form -->
    <div id="joinotify-builder-variable-form" class="rounded-lg border border-slate-200 bg-slate-50/60 p-6">
      <div class="mb-4 flex items-center justify-between">
        <h4 class="text-[14px] font-semibold text-slate-800">
          {{ isEditing ? __('Edit variable', textDomain) : __('New variable', textDomain) }}
        </h4>
        <code class="rounded bg-white px-2 py-1 text-[12px] font-semibold text-primary-700 ring-1 ring-slate-200">{{ tokenPreview }}</code>
      </div>

      <div class="grid gap-5 md:grid-cols-2">
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
        <div class="flex flex-wrap gap-2">
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

      <div class="mt-6 flex items-center gap-3">
        <button
          type="button"
          class="rounded-[8px] bg-primary-600 px-6 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="saving || !draft.token || !draft.post_type || !draft.meta_key"
          @click="onSave"
        >
          {{ saving ? __('Saving…', textDomain) : (isEditing ? __('Update variable', textDomain) : __('Add variable', textDomain)) }}
        </button>
        <button
          v-if="isEditing"
          type="button"
          class="rounded-[8px] border border-slate-200 px-6 py-3 text-[14px] font-medium text-slate-600 transition hover:bg-white"
          @click="resetDraft"
        >
          {{ __('Cancel', textDomain) }}
        </button>
      </div>
    </div>
  </div>
</template>
