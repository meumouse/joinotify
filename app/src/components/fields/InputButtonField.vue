<script setup>

/**
 * InputButtonField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import InputGroupField from './InputGroupField.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  name: { type: String, required: true },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
  showHeader: { type: Boolean, default: true },
  inputClass: { type: [String, Array, Object], default: '' },
  groupClass: { type: [String, Array, Object], default: '' },
  addonClass: { type: [String, Array, Object], default: '' },
  wrapperClass: { type: [String, Array, Object], default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <InputGroupField
    v-bind="props"
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <template #actions>
      <slot name="actions" />
    </template>
  </InputGroupField>
</template>
