<script setup>
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
