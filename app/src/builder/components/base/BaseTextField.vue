<script setup lang="ts">
/**
 * BaseTextField.vue
 *
 * Thin wrapper around the shared BaseInput that adapts the text input for
 * builder settings. Serves as the base control for the date, time and number
 * field variants, forwarding common props and relaying input and change events.
 *
 * @since 2.0.0
 */
import BaseInput from '../../../components/base/BaseInput.vue';

defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'input', 'change']);
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    :id="id"
    :name="name"
    :label="label"
    :placeholder="placeholder"
    :type="type"
    :disabled="disabled"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
