<script setup lang="ts">
import BaseSwitch from '../../../components/base/BaseSwitch.vue';

defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'change']);
</script>

<template>
  <BaseSwitch
    :model-value="modelValue"
    :label="label"
    :disabled="disabled"
    :loading="loading"
    @update:model-value="$emit('update:modelValue', $event)"
    @change="$emit('change', $event)"
  />
</template>
