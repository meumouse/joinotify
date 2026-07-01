<script setup lang="ts">
/**
 * BaseDateField.vue
 *
 * Thin wrapper around BaseTextField that fixes the input type to "date",
 * providing a labelled date picker with v-model support for builder settings.
 *
 * @since 2.0.0
 */
import BaseTextField from './BaseTextField.vue';

defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'input', 'change']);
</script>

<template>
  <BaseTextField
    :model-value="modelValue"
    :id="id"
    :name="name"
    :label="label"
    type="date"
    :disabled="disabled"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
