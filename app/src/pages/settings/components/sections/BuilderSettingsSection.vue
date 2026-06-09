<script setup>

/**
 * BuilderSettingsSection.vue frontend component.
 *
 * Lists custom text variables (placeholders) mapped to a post type + meta key
 * and opens a modal to create or edit them.
 *
 * @since 2.0.0
 */
import { ref } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import BuilderVariableModal from '../modals/BuilderVariableModal.vue';

const props = defineProps({
  items: { type: Array, default: () => [] },
  postTypes: { type: Array, default: () => [] },
  saveVariable: { type: Function, default: null },
  deleteVariable: { type: Function, default: null },
  loadMetaKeys: { type: Function, default: null },
});

const modalOpen = ref(false);
const editingVariable = ref(null);
const saving = ref(false);

function displayToken(token) {
  return '{{ ' + (token || '') + ' }}';
}

function postTypeLabel(slug) {
  const match = (props.postTypes || []).find((option) => option.value === slug);
  return match ? match.label : slug;
}

function openCreate() {
  editingVariable.value = null;
  modalOpen.value = true;
}

function openEdit(item) {
  editingVariable.value = { ...item };
  modalOpen.value = true;
}

function closeModal() {
  modalOpen.value = false;
  editingVariable.value = null;
}

async function onModalSave(payload) {
  if (typeof props.saveVariable !== 'function') {
    return;
  }

  saving.value = true;

  try {
    const ok = await props.saveVariable(payload);

    if (ok) {
      closeModal();
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
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Custom variables', textDomain) }}</h3>
        <p class="mt-1 max-w-2xl text-[13px] leading-5 text-slate-500">
          {{ __('Map an entity (post type) and a meta key to a named variable. The variable becomes available in the flow builder and is replaced at runtime with the matching post meta value.', textDomain) }}
        </p>
      </div>

      <button
        type="button"
        class="inline-flex shrink-0 items-center gap-2 rounded-[8px] bg-primary-600 px-5 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-700"
        @click="openCreate"
      >
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M10 4.5v11M4.5 10h11" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
        </svg>
        {{ __('Add variable', textDomain) }}
      </button>
    </div>

    <!-- Existing variables -->
    <div v-if="!items.length" class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-[14px] text-slate-500">
      {{ __('No custom variable yet. Click “Add variable” to create your first one.', textDomain) }}
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
          @click="openEdit(item)"
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

    <BuilderVariableModal
      :open="modalOpen"
      :variable="editingVariable"
      :post-types="postTypes"
      :saving="saving"
      :load-meta-keys="loadMetaKeys"
      @close="closeModal"
      @save="onModalSave"
    />
  </div>
</template>
