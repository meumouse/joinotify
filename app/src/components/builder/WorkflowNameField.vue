<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseInput from '../base/BaseInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    :label="__('Workflow name', textDomain)"
    :placeholder="__('Workflow name', textDomain)"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
