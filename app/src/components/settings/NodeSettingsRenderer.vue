<script setup lang="ts">
/**
 * NodeSettingsRenderer.vue
 *
 * Renders the settings editor for the selected workflow node. Keeps a local
 * draft buffer of the node's data, resolves the trigger or action definition,
 * renders schema-driven trigger fields or the dynamic action settings, and
 * emits update events back to the store while avoiding feedback loops.
 *
 * @since 2.0.0
 */
import { computed, nextTick, ref, watch } from 'vue';
import SchemaFieldRenderer from './SchemaFieldRenderer.vue';
import DynamicActionSettingsRenderer from '../../builder/actions/components/DynamicActionSettingsRenderer.vue';
import SessionWindowNotice from '../../builder/components/base/SessionWindowNotice.vue';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';
import { getActionDefinition as getLegacyActionDefinition } from '../../registries/actionRegistry';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import { getTriggerSettingsSchema } from '../../utils/triggerSettings';
import { cloneSerializable, findWorkflowNodeLocation } from '../../utils/workflowTree';
import { __, textDomain } from '../../utils/i18n';
import type { WorkflowNode } from '../../types/workflowBuilder';

// Tokens the loop action exposes to the actions nested in its body. Offered in the
// placeholder picker (and used to enable the "loop item file" attachment) only when
// the edited node actually lives inside a loop. Mirrors the runtime resolution in
// admin/src/Core/Functions.php (joinotify_prepare_message).
const LOOP_PLACEHOLDERS: Array<{ placeholder: string; description: string }> = [
  { placeholder: '{{ loop_value }}', description: __('Current item value (file/product name or list entry).', textDomain) },
  { placeholder: '{{ loop_number }}', description: __('Current iteration number, starting at 1.', textDomain) },
  { placeholder: '{{ loop_index }}', description: __('Current iteration index, starting at 0.', textDomain) },
  { placeholder: '{{ loop_count }}', description: __('Total number of items in the collection.', textDomain) },
  { placeholder: '{{ loop_file_name }}', description: __('Digital file name (order files loop).', textDomain) },
  { placeholder: '{{ loop_download_url }}', description: __('Digital file download link (order files loop).', textDomain) },
  { placeholder: '{{ loop_product_name }}', description: __('Product name of the current item.', textDomain) },
  { placeholder: '{{ loop_item_name }}', description: __('Purchased item name (order items loop).', textDomain) },
  { placeholder: '{{ loop_item_quantity }}', description: __('Purchased item quantity (order items loop).', textDomain) },
];

const props = defineProps<{
  node: WorkflowNode | null;
  contexts: Array<{ id: string; label: string; description?: string; icon?: string; icon_svg?: string }>;
}>();

const emit = defineEmits<{
  (event: 'update', value: Record<string, unknown>): void;
}>();

const store = useWorkflowBuilderStore();
const draft = ref<Record<string, unknown>>({});
const isSyncingFromNode = ref(false);
const lastEmittedSnapshot = ref('');

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

const triggerSettingsSchema = computed(() =>
  isTrigger.value ? getTriggerSettingsSchema(actionDefinition.value) : []
);

const draftSettings = computed<Record<string, unknown>>(() =>
  draft.value.settings && typeof draft.value.settings === 'object'
    ? (draft.value.settings as Record<string, unknown>)
    : {}
);

/**
 * Update a single key within the draft's settings object immutably.
 *
 * @since 2.0.0
 * @param {string} key Settings key to update.
 * @param {unknown} value New value for the key.
 * @returns {void}
 */
function updateSettingField(key: string, value: unknown) {
  draft.value = {
    ...draft.value,
    settings: {
      ...draftSettings.value,
      [key]: value,
    },
  };
}

// The workflow's trigger slug, used to flag placeholders that are valid tokens
// but not provided by the current trigger's context.
const currentTriggerSlug = computed(() =>
  String(store.triggerNode?.data?.trigger || store.selectedTrigger || '').trim()
);

// Whether the edited node lives inside a loop body (any loop ancestor). Drives the
// loop-scoped placeholder tokens and the "current loop item file" attachment source.
const isInsideLoop = computed(() => {
  const nodes = store.workflowContent;

  if (!props.node || !Array.isArray(nodes)) {
    return false;
  }

  let currentId: string | null = props.node.id;
  const guard = new Set<string>();

  while (currentId && !guard.has(currentId)) {
    guard.add(currentId);
    const location = findWorkflowNodeLocation(nodes, currentId);

    if (!location || !location.parent) {
      break;
    }

    if (String(location.parent.data?.action || '') === 'loop') {
      return true;
    }

    currentId = location.parent.id;
  }

  return false;
});

const placeholderItems = computed(() => {
  const trigger = currentTriggerSlug.value;

  const catalogItems = (store.placeholderCatalog || []).flatMap((group) =>
    (group.items || []).map((item) => {
      const triggers = Array.isArray(item.triggers) ? item.triggers : [];
      // An empty `triggers` list means a global placeholder, available in every
      // context. Otherwise it resolves only when the current trigger is listed.
      // When the trigger is still unknown, assume available so we never warn blindly.
      const available = !trigger || triggers.length === 0 || triggers.includes(trigger);

      return {
        placeholder: item.placeholder,
        description: item.description,
        available,
      };
    })
  );

  // loop tokens only resolve inside a loop body, so offer them only there
  if (isInsideLoop.value) {
    return [
      ...LOOP_PLACEHOLDERS.map((item) => ({ ...item, available: true })),
      ...catalogItems,
    ];
  }

  return catalogItems;
});

// Re-sync the edit buffer only when the *selected node changes identity* — not on
// every deep mutation of the current node's data. The canvas continuously syncs
// node positions (canvas_position) into the store while this modal is open; with a
// deep watcher, such a background mutation lands in the same flush as a keystroke,
// runs first, flips isSyncingFromNode true (across its `await nextTick`), and the
// draft watcher below then skips emitting the user's edit — silently dropping it.
// Keying on node id keeps the buffer authoritative for the open node.
watch(
  () => props.node?.id || null,
  async () => {
    isSyncingFromNode.value = true;
    draft.value = cloneSerializable(props.node?.data || {});
    lastEmittedSnapshot.value = JSON.stringify(draft.value || {});
    await nextTick();
    isSyncingFromNode.value = false;
  },
  { immediate: true }
);

watch(
  () => draft.value,
  (value) => {
    if (isSyncingFromNode.value) {
      return;
    }

    const snapshot = JSON.stringify(value || {});

    if (snapshot === lastEmittedSnapshot.value) {
      return;
    }

    lastEmittedSnapshot.value = snapshot;
    emit('update', cloneSerializable(value || {}));
  },
  { deep: true }
);

/**
 * Replace the entire draft buffer with a serializable clone of the given value.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} value New node data to store in the draft.
 * @returns {void}
 */
function replaceDraft(value: Record<string, unknown>) {
  draft.value = cloneSerializable(value || {});
}

/**
 * Copy a placeholder token to the clipboard, ignoring failures in restricted contexts.
 *
 * @since 2.0.0
 * @param {string} placeholder Placeholder token to copy.
 * @returns {Promise<void>} Resolves once the copy attempt completes.
 */
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
</script>

<template>
  <div v-if="node" class="space-y-6">
    <template v-if="isTrigger">
      <div
        v-if="triggerSettingsSchema.length"
        class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div>
          <h4 class="text-sm font-semibold text-slate-900">{{ __('Trigger settings', textDomain) }}</h4>
          <p class="mt-1 text-sm leading-6 text-slate-500">
            {{ __('Required configuration for this trigger to run correctly.', textDomain) }}
          </p>
        </div>

        <SchemaFieldRenderer
          v-for="field in triggerSettingsSchema"
          :key="field.key"
          :field="field"
          :model-value="draftSettings[field.key]"
          :root-value="draftSettings"
          @update:model-value="updateSettingField(field.key, $event)"
        />
      </div>

      <div
        v-else
        class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500"
      >
        {{ __('This trigger has no additional settings. Use “Change trigger” in the node menu to pick a different one.', textDomain) }}
      </div>
    </template>

    <template v-else>
      <SessionWindowNotice :action="actionSlug" />

      <DynamicActionSettingsRenderer
        :action="actionSlug"
        :model-value="draft"
        :available-placeholders="placeholderItems"
        :cron-available="Boolean(store.bootstrap?.permissions?.cron_available ?? true)"
        @update:model-value="replaceDraft"
        @placeholder-selected="copyPlaceholder"
      />
    </template>
  </div>

  <div v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
    {{ __('Select a node to edit its settings.', textDomain) }}
  </div>
</template>
