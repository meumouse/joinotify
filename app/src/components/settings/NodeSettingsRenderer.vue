<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BaseButton from '../base/BaseButton.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseSelect from '../base/BaseSelect.vue';
import BaseTextarea from '../base/BaseTextarea.vue';
import SchemaFieldRenderer from './SchemaFieldRenderer.vue';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';
import { getActionDefinition } from '../../registries/actionRegistry';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import { cloneSerializable, isConditionNode, isDelayNode, isPlaceholderNode, isSnippetNode, isStopNode } from '../../utils/workflowTree';
import type { WorkflowNode, WorkflowPlaceholderGroup, WorkflowRegistryItem } from '../../types/workflowBuilder';

const props = defineProps<{
  node: WorkflowNode | null;
  contexts: Array<{ id: string; label: string; description?: string; icon?: string; icon_svg?: string }>;
}>();

const emit = defineEmits<{
  (event: 'update', value: Record<string, unknown>): void;
}>();

const store = useWorkflowBuilderStore();
const draft = ref<Record<string, unknown>>({});
const copiedPlaceholder = ref('');

const triggerContexts = computed(() => props.contexts || []);
const selectedContext = computed(() => String(draft.value.context || props.node?.data.context || ''));
const selectedTrigger = computed(() => String(draft.value.trigger || props.node?.data.trigger || ''));
const selectedAction = computed(() => String(draft.value.action || props.node?.data.action || ''));

const definition = computed<WorkflowRegistryItem | undefined>(() => {
  if (!props.node) {
    return undefined;
  }

  if (props.node.type === 'trigger') {
    return getTriggerDefinition(selectedContext.value, selectedTrigger.value);
  }

  return getActionDefinition(selectedAction.value);
});

const fieldSchema = computed(() => definition.value?.schema || []);
const previewText = computed(() => {
  if (!props.node) {
    return '';
  }

  const registry = definition.value;

  if (registry?.preview) {
    return registry.preview(draft.value);
  }

  if (props.node.type === 'trigger') {
    return `${String(draft.value.title || props.node.data.title || 'Trigger')} · ${String(draft.value.trigger || props.node.data.trigger || '')}`;
  }

  if (isConditionNode(props.node)) {
    return `${String(draft.value.title || 'Condition')} · ${String(draft.value.condition || '')}`;
  }

  if (isDelayNode(props.node)) {
    const value = String(draft.value.delay_value || '');
    const period = String(draft.value.delay_period || '');
    return value ? `${value} ${period}` : 'Delay';
  }

  if (isStopNode(props.node)) {
    return 'Stops the workflow';
  }

  if (isSnippetNode(props.node)) {
    return 'PHP snippet';
  }

  if (isPlaceholderNode(props.node)) {
    return String(draft.value.dynamic_placeholder_text || 'Dynamic placeholder');
  }

  return String(draft.value.description || draft.value.message || definition.value?.description || '');
});

const validationErrors = computed(() => {
  if (!definition.value?.validate) {
    return [];
  }

  return definition.value.validate(draft.value);
});

const placeholderGroups = computed<WorkflowPlaceholderGroup[]>(() => store.placeholderCatalog || []);
const triggerOptions = computed(() => store.getTriggersForContext(selectedContext.value));

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

async function copyPlaceholder(placeholder: string) {
  if (!placeholder) {
    return;
  }

  try {
    await navigator.clipboard.writeText(placeholder);
    copiedPlaceholder.value = placeholder;
    window.setTimeout(() => {
      if (copiedPlaceholder.value === placeholder) {
        copiedPlaceholder.value = '';
      }
    }, 1200);
  } catch {
    copiedPlaceholder.value = '';
  }
}

function getPlaceholderBadge(group: WorkflowPlaceholderGroup) {
  return group.label || group.id || 'General';
}
</script>

<template>
  <div v-if="node" class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
            {{ node.type === 'trigger' ? 'Trigger settings' : 'Action settings' }}
          </p>
          <h3 class="mt-2 text-base font-semibold text-slate-900">
            {{ draft.title || definition?.label || node.data.title || 'Node' }}
          </h3>
        </div>

        <span class="inline-flex rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
          {{ node.type }}
        </span>
      </div>

      <p v-if="previewText" class="mt-3 text-sm leading-6 text-slate-600">
        {{ previewText }}
      </p>
    </div>

    <div v-if="node.type === 'trigger'" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <BaseInput
        :model-value="String(draft.title || '')"
        :label="'Workflow name'"
        :placeholder="'Workflow name'"
        @update:model-value="updateField('title', $event)"
      />

      <BaseSelect
        :model-value="String(draft.context || '')"
        :label="'Integration'"
        :options="triggerContexts.map((item) => ({ label: item.label, value: item.id }))"
        placeholder="Select integration"
        @update:model-value="updateField('context', $event)"
      />

      <BaseSelect
        :model-value="String(draft.trigger || '')"
        :label="'Trigger'"
        :options="triggerOptions.map((item) => ({ label: item.label, value: item.id }))"
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

    <div v-else class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput
          :model-value="String(draft.title || '')"
          :label="'Title'"
          :placeholder="definition?.label || 'Action title'"
          @update:model-value="updateField('title', $event)"
        />

        <BaseInput
          :model-value="String(draft.action || '')"
          :label="'Action slug'"
          placeholder="action_slug"
          :disabled="true"
        />
      </div>

      <BaseTextarea
        :model-value="String(draft.description || '')"
        :label="'Preview description'"
        :rows="3"
        placeholder="Action description"
        @update:model-value="updateField('description', $event)"
      />
    </div>

    <div v-if="fieldSchema.length" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h4 class="text-sm font-semibold text-slate-900">Dynamic fields</h4>
          <p class="mt-1 text-sm leading-6 text-slate-500">
            Rendered from the registry schema for the selected node.
          </p>
        </div>
      </div>

      <SchemaFieldRenderer
        v-for="field in fieldSchema"
        :key="field.key"
        :field="field"
        :model-value="draft[field.key]"
        :root-value="draft"
        @update:model-value="updateField(field.key, $event)"
      />
    </div>

    <div class="space-y-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between gap-3">
        <h4 class="text-sm font-semibold text-slate-900">Placeholders</h4>
        <span class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500">
          {{ placeholderGroups.length }} groups
        </span>
      </div>

      <div v-if="placeholderGroups.length" class="space-y-3">
        <details
          v-for="group in placeholderGroups"
          :key="group.id"
          class="group rounded-2xl border border-slate-200 bg-slate-50/80 p-4"
          open
        >
          <summary class="cursor-pointer list-none text-sm font-semibold text-slate-900">
            {{ getPlaceholderBadge(group) }}
          </summary>

          <div class="mt-4 space-y-3">
            <button
              v-for="item in group.items"
              :key="item.placeholder"
              type="button"
              class="flex w-full items-start justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-primary-200 hover:shadow-md"
              @click="copyPlaceholder(item.placeholder)"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <code class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-primary-700">{{ item.placeholder }}</code>
                  <span
                    v-if="copiedPlaceholder === item.placeholder"
                    class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-700"
                  >
                    Copied
                  </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.description }}</p>
              </div>

              <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Copy</span>
            </button>
          </div>
        </details>
      </div>

      <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
        No placeholders available for this trigger.
      </div>
    </div>

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

    <div v-if="definition?.description" class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
      <h4 class="text-sm font-semibold text-slate-900">Registry summary</h4>
      <p class="mt-2 text-sm leading-6 text-slate-600">{{ definition.description }}</p>
    </div>
  </div>

  <div v-else class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
    Select a node to edit its settings.
  </div>
</template>

