<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseDrawer from '../../../components/base/BaseDrawer.vue';
import BaseAlert from '../../components/base/BaseAlert.vue';
import DynamicActionSettingsRenderer from './DynamicActionSettingsRenderer.vue';
import { useActionRegistry } from '../composables/useActionRegistry';

const props = defineProps({
  open: { type: Boolean, default: false },
  action: { type: String, default: '' },
  modelValue: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'save', 'update:modelValue']);

const registry = useActionRegistry();
const draft = ref<Record<string, unknown>>({});

watch(
  () => props.modelValue,
  (value) => {
    draft.value = { ...(value as Record<string, unknown>) };
  },
  { deep: true, immediate: true }
);

const definition = computed(() => registry.get(props.action));
const validationErrors = computed(() => {
  const validate = definition.value?.validate;
  return validate ? validate(draft.value) : {};
});
const canSave = computed(() => Object.keys(validationErrors.value || {}).length === 0);

async function copyPlaceholder(placeholder: string) {
  if (!placeholder || typeof navigator === 'undefined' || !navigator.clipboard) {
    return;
  }

  try {
    await navigator.clipboard.writeText(placeholder);
  } catch {
    // Ignore clipboard failures in restricted browser contexts.
  }
}

function updateDraft(nextValue: Record<string, unknown>) {
  draft.value = { ...nextValue };
  emit('update:modelValue', { ...draft.value });
}

function save() {
  if (!canSave.value || props.loading) {
    return;
  }

  emit('save', { ...draft.value });
}
</script>

<template>
  <BaseDrawer :open="open" :title="definition?.title || 'Action settings'" @close="$emit('close')">
    <div class="space-y-5">
      <BaseAlert
        v-if="definition?.description"
        tone="neutral"
        :title="definition.title"
        :message="definition.description"
      />

      <DynamicActionSettingsRenderer
        :action="action"
        :model-value="draft"
        :available-placeholders="availablePlaceholders"
        :cron-available="cronAvailable"
        @update:model-value="updateDraft"
        @placeholder-selected="copyPlaceholder"
      />

      <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
        <BaseButton title="Cancel" variant="ghost" :disabled="loading" @click="$emit('close')" />
        <BaseButton title="Save" :loading="loading" :disabled="!canSave" @click="save" />
      </div>
    </div>
  </BaseDrawer>
</template>
