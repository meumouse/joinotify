<script setup lang="ts">
/**
 * DynamicActionSettingsRenderer.vue
 *
 * Resolves the current action's registry definition and renders its settings UI.
 *
 * Resolution order (with graceful, backward-compatible fallbacks):
 *   1. A registered Vue `settingsComponent` (the rich, bespoke path used by the
 *      built-in actions) — rendered dynamically, unchanged from previous behavior.
 *   2. When no component is registered but the action declares a `settingsSchema`,
 *      the schema is rendered generically through SchemaFieldRenderer — the same
 *      data-driven path the trigger settings already use. This lets a third-party
 *      action drive its whole settings form from PHP (`settings_schema`) with NO
 *      JavaScript, as documented in DEVELOPERS.md.
 *   3. When neither is available, a neutral notice is shown and the workflow can
 *      still be saved safely.
 *
 * It also normalizes the available placeholder list (flat, grouped, or string
 * entries) into a uniform shape and forwards model-value and placeholder events
 * to the parent editor.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import SchemaFieldRenderer from '../../../components/settings/SchemaFieldRenderer.vue';
import BaseAlert from '../../components/base/BaseAlert.vue';
import { __, textDomain } from '../../../utils/i18n';
import type { WorkflowFieldSchema } from '../../../types/workflowBuilder';

const props = defineProps({
  action: { type: String, default: '' },
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const registry = useActionRegistry();

const definition = computed(() => registry.get(props.action));
const settingsComponent = computed(() => definition.value?.settingsComponent);

// The declarative schema fallback is only used when no bespoke Vue component is
// registered for the action, preserving the existing component-first behavior.
const settingsSchema = computed<WorkflowFieldSchema[]>(() => {
  const schema = definition.value?.settingsSchema;

  return Array.isArray(schema) ? (schema as WorkflowFieldSchema[]) : [];
});

const hasSchemaFallback = computed(() => !settingsComponent.value && settingsSchema.value.length > 0);

/**
 * Read the value bound to a schema field from the flat action data draft.
 *
 * Action data is stored as flat top-level keys (e.g. `message`, `receiver`),
 * matching how `default_data`/`settings_schema` map keys in the PHP catalog.
 *
 * @since 2.0.0
 * @param {string} key Schema field key.
 * @returns {unknown} The current value for the key, or undefined.
 */
function readFieldValue(key: string): unknown {
  const source = props.modelValue as Record<string, unknown>;

  return source ? source[key] : undefined;
}

/**
 * Emit an immutable copy of the action data draft with a single field updated.
 *
 * @since 2.0.0
 * @param {string} key Schema field key to set.
 * @param {unknown} value New value for the field.
 * @returns {void}
 */
function updateFieldValue(key: string, value: unknown): void {
  const source = props.modelValue && typeof props.modelValue === 'object' ? props.modelValue : {};

  emit('update:modelValue', {
    ...(source as Record<string, unknown>),
    [key]: value,
  });
}

const placeholderItems = computed(() => {
  const source = Array.isArray(props.availablePlaceholders) ? props.availablePlaceholders : [];
  const items: Array<{ placeholder: string; description?: string; available?: boolean }> = [];

  // Preserve the context-availability flag computed upstream so unavailable
  // variables keep their warning style/tooltip in the action settings fields.
  /**
   * Reads the optional context-availability flag from a placeholder entry.
   *
   * @since 2.0.0
   * @param {Record<string, unknown>} entry Raw placeholder entry.
   * @returns {boolean|undefined} The availability flag when present, otherwise undefined.
   */
  const readAvailable = (entry: Record<string, unknown>): boolean | undefined =>
    typeof entry.available === 'boolean' ? entry.available : undefined;

  for (const entry of source as Array<Record<string, unknown> | string>) {
    if (typeof entry === 'string') {
      items.push({ placeholder: entry });
      continue;
    }

    if (entry && typeof entry === 'object' && Array.isArray(entry.items)) {
      for (const nested of entry.items as Array<Record<string, unknown> | string>) {
        if (typeof nested === 'string') {
          items.push({ placeholder: nested });
          continue;
        }

        const placeholder = String((nested as Record<string, unknown>).placeholder || '');
        if (placeholder) {
          items.push({
            placeholder,
            description: String((nested as Record<string, unknown>).description || ''),
            available: readAvailable(nested as Record<string, unknown>),
          });
        }
      }
      continue;
    }

    const placeholder = String(entry.placeholder || entry.placeholder_text || '');
    if (placeholder) {
      items.push({
        placeholder,
        description: String(entry.description || ''),
        available: readAvailable(entry),
      });
    }
  }

  return items;
});
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      v-if="!action"
      tone="neutral"
      :title="__('No action selected', textDomain)"
      :message="__('Select a workflow action to configure it.', textDomain)"
    />

    <template v-else>
      <component
        :is="settingsComponent"
        v-if="settingsComponent"
        :model-value="modelValue"
        :available-placeholders="placeholderItems"
        :cron-available="cronAvailable"
        @update:model-value="$emit('update:modelValue', $event)"
        @placeholder-selected="$emit('placeholder-selected', $event)"
      />

      <!--
        Declarative fallback: render the PHP-declared settings_schema generically,
        the same way trigger settings are rendered. No per-action Vue component
        required for standard field types.
      -->
      <div v-else-if="hasSchemaFallback" class="space-y-4">
        <SchemaFieldRenderer
          v-for="field in settingsSchema"
          :key="field.key"
          :field="field"
          :model-value="readFieldValue(field.key)"
          :root-value="(modelValue as Record<string, unknown>)"
          @update:model-value="updateFieldValue(field.key, $event)"
        />
      </div>

      <BaseAlert
        v-else
        tone="warning"
        :title="__('Configuration component not available', textDomain)"
        :message="__('This action is registered, but the frontend component is not available yet. The workflow can still be saved safely.', textDomain)"
      />
    </template>
  </div>
</template>
