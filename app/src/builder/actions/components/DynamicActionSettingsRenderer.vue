<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import BaseAlert from '../../components/base/BaseAlert.vue';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  action: { type: String, default: '' },
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

defineEmits(['update:modelValue', 'placeholder-selected']);

const registry = useActionRegistry();

const definition = computed(() => registry.get(props.action));
const settingsComponent = computed(() => definition.value?.settingsComponent);
const placeholderItems = computed(() => {
  const source = Array.isArray(props.availablePlaceholders) ? props.availablePlaceholders : [];
  const items: Array<{ placeholder: string; description?: string; available?: boolean }> = [];

  // Preserve the context-availability flag computed upstream so unavailable
  // variables keep their warning style/tooltip in the action settings fields.
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

      <BaseAlert
        v-else
        tone="warning"
        :title="__('Configuration component not available', textDomain)"
        :message="__('This action is registered, but the frontend component is not available yet. The workflow can still be saved safely.', textDomain)"
      />
    </template>
  </div>
</template>
