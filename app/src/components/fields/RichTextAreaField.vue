<script setup lang="ts">
/**
 * RichTextAreaField.vue
 *
 * Schema-driven field wrapper that adapts a workflow field definition to the
 * BaseRichTextArea component. Maps the field's label, description, placeholder,
 * and rows into props and forwards v-model/input/change events upward.
 *
 * @since 2.0.0
 */
import BaseRichTextArea from '../base/BaseRichTextArea.vue';

defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  showHeader: { type: Boolean, default: true },
});

defineEmits(['update:modelValue', 'input', 'change']);
</script>

<template>
  <BaseRichTextArea
    :model-value="String(modelValue || '')"
    :id="name"
    :name="name"
    :label="showHeader ? field.label : ''"
    :description="showHeader ? field.description : ''"
    :placeholder="field.placeholder || ''"
    :disabled="field.disabled || false"
    :rows="field.rows || 4"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
