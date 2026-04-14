<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseSearchInput from '../base/BaseSearchInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseSearchInput
    :model-value="modelValue"
    :placeholder="__('Search templates', textDomain)"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
