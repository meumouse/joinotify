<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseInput from './BaseInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: () => __('Search', textDomain) },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    :placeholder="placeholder"
    type="search"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
