<script setup>
/**
 * WorkflowNameField.vue
 *
 * Thin wrapper around BaseInput for editing the workflow's internal name. Falls
 * back to using the label as the placeholder when none is provided and forwards
 * the value through v-model.
 *
 * @since 2.0.0
 */
import BaseInput from '../base/BaseInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    :label="label"
    :placeholder="placeholder || label"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
