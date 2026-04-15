<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BaseAlert from '../../builder/components/base/BaseAlert.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseSelect from '../base/BaseSelect.vue';
import BaseTextarea from '../base/BaseTextarea.vue';
import DynamicActionSettingsRenderer from '../../builder/actions/components/DynamicActionSettingsRenderer.vue';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';
import { getActionDefinition as getLegacyActionDefinition } from '../../registries/actionRegistry';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import { cloneSerializable } from '../../utils/workflowTree';
import type { WorkflowNode } from '../../types/workflowBuilder';

const props = defineProps<{
  node: WorkflowNode | null;
  contexts: Array<{ id: string; label: string; description?: string; icon?: string; icon_svg?: string }>;
}>();

const emit = defineEmits<{
  (event: 'update', value: Record<string, unknown>): void;
}>();

const store = useWorkflowBuilderStore();
const draft = ref<Record<string, unknown>>({});

const isTrigger = computed(() => props.node?.type === 'trigger');
const actionSlug = computed(() => String(props.node?.data?.action || ''));
const actionDefinition = computed(() => {
  if (!props.node) {
    return undefined;
  }

  if (isTrigger.value) {
    const context = String(draft.value.context || props.node.data.context || '');
    const trigger = String(draft.value.trigger || props.node.data.trigger || '');
    return getTriggerDefinition(context, trigger);
  }

  return getLegacyActionDefinition(actionSlug.value);
});

const placeholderItems = computed(() =>
  (store.placeholderCatalog || []).flatMap((group) =>
    (group.items || []).map((item) => ({
      placeholder: item.placeholder,
      description: item.description,
    }))
  )
);

const validationErrors = computed(() => {
  const validator = actionDefinition.value?.validate;

  if (!validator) {
    return [];
  }

  const result = validator(draft.value);

  if (Array.isArray(result)) {
    return result;
  }

  return Object.values(result || {});
});

watch(
  () => props.node,
  (node) => {
    draft.value = cloneSerializable(node?.data || {});
  },
  { immediate: true, deep: true }
);

watch(
  () => draft.value,
  (value) => {
    emit('update', cloneSerializable(value || {}));
  },
  { deep: true }
);

function updateField(key: string, value: unknown) {
  draft.value = {
    ...draft.value,
    [key]: value,
  };
}

function replaceDraft(value: Record<string, unknown>) {
  draft.value = cloneSerializable(value || {});
}

async function copyPlaceholder(placeholder: string) {
  if (!placeholder || typeof navigator === 'undefined' || !navigator.clipboard) {
    return;
  }

  try {
    await navigator.clipboard.writeText(placeholder);
  } catch {
    // Clipboard access can fail in restricted contexts. Ignore safely.
  }
}

function triggerContexts() {
  return props.contexts || [];
}

function triggerOptions() {
  const context = String(draft.value.context || props.node?.data.context || '');
  return store.getTriggersForContext(context);
}

const summaryText = computed(() => {
  if (!props.node) {
    return '';
  }

  if (isTrigger.value) {
    const title = String(draft.value.title || props.node.data.title || 'Trigger');
    const trigger = String(draft.value.trigger || props.node.data.trigger || '');
    return trigger ? `${title} · ${trigger}` : title;
  }

  const preview = actionDefinition.value?.preview;

  if (preview) {
    return preview(draft.value);
  }

  return String(draft.value.description || '');
});
</script>

<template>
  <div v-if="node" class="space-y-6">
    <BaseAlert
      v-if="summaryText"
      tone="neutral"
      :title="String(actionDefinition?.title || node.data?.title || (isTrigger ? 'Trigger' : 'Action'))"
      :message="summaryText"
    />

    <div v-if="isTrigger" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <BaseInput
        :model-value="String(draft.title || '')"
        :label="'Workflow name'"
        :placeholder="'Workflow name'"
        @update:model-value="updateField('title', $event)"
      />

      <BaseSelect
        :model-value="String(draft.context || '')"
        :label="'Integration'"
        :options="triggerContexts().map((item) => ({ label: item.label, value: item.id }))"
        placeholder="Select integration"
        @update:model-value="updateField('context', $event)"
      />

      <BaseSelect
        :model-value="String(draft.trigger || '')"
        :label="'Trigger'"
        :options="triggerOptions().map((item) => ({ label: item.label, value: item.id }))"
        placeholder="Select trigger"
        @update:model-value="updateField('trigger', $event)"
      />

      <BaseTextarea
        :model-value="String(draft.description || '')"
        :label="'Description'"
        :rows="4"
        placeholder="Internal summary"
        @update:model-value="updateField('description', $event)"
      />
    </div>

    <DynamicActionSettingsRenderer
      v-else
      :action="actionSlug"
      :model-value="draft"
      :available-placeholders="placeholderItems"
      :cron-available="Boolean(store.bootstrap?.permissions?.cron_available ?? true)"
      @update:model-value="replaceDraft"
      @placeholder-selected="copyPlaceholder"
    />

    <div v-if="validationErrors.length" class="space-y-2 rounded-3xl border border-rose-200 bg-rose-50 p-5">
      <h4 class="text-sm font-semibold text-rose-900">Validation</h4>
      <p
        v-for="error in validationErrors"
        :key="error"
        class="text-sm leading-6 text-rose-700"
      >
        {{ error }}
      </p>
    </div>
  </div>

  <div v-else class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
    Select a node to edit its settings.
  </div>
</template>
