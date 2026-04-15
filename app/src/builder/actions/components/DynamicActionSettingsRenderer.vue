<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseTextareaField from '../../components/base/BaseTextareaField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';

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
const validationErrors = computed(() => {
  const validate = definition.value?.validate;
  return validate ? validate(props.modelValue || {}) : {};
});
const placeholderItems = computed(() => {
  const source = Array.isArray(props.availablePlaceholders) ? props.availablePlaceholders : [];
  const items: Array<{ placeholder: string; description?: string }> = [];

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
      });
    }
  }

  return items;
});

function updateField(key: string, value: unknown) {
  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    [key]: value,
  });
}
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      v-if="!action"
      tone="neutral"
      title="No action selected"
      message="Select a workflow action to configure it."
    />

    <template v-else>
      <FieldGroup title="Shared fields" description="These fields are common to every action.">
        <BaseTextField
          :model-value="String(modelValue.title || '')"
          label="Title"
          placeholder="Action title"
          @update:model-value="updateField('title', $event)"
        />
        <BaseTextareaField
          :model-value="String(modelValue.description || '')"
          label="Description"
          placeholder="Action summary"
          :rows="3"
          @update:model-value="updateField('description', $event)"
        />
      </FieldGroup>

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
        title="Configuration component not available"
        message="This action is registered, but the frontend component is not available yet. The workflow can still be saved safely."
      />

      <BaseAlert
        v-if="Object.keys(validationErrors || {}).length"
        tone="danger"
        title="Validation"
      >
        <p
          v-for="(message, key) in validationErrors"
          :key="key"
          class="mt-1"
        >
          {{ message }}
        </p>
      </BaseAlert>
    </template>
  </div>
</template>
