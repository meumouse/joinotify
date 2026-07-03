<script setup lang="ts">
/**
 * BaseNumberField.vue
 *
 * Thin wrapper around BaseTextField that fixes the input type to "number" and
 * exposes min, max and step props, providing a numeric field with v-model
 * support for builder settings.
 *
 * @since 2.0.0
 */
import BaseTextField from './BaseTextField.vue';

defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  min: { type: [String, Number], default: undefined },
  max: { type: [String, Number], default: undefined },
  step: { type: [String, Number], default: undefined },
});

defineEmits(['update:modelValue', 'input', 'change']);
</script>

<template>
  <BaseTextField
    :model-value="modelValue"
    :id="id"
    :name="name"
    :label="label"
    :placeholder="placeholder"
    type="number"
    :disabled="disabled"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
