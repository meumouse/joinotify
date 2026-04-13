<script setup>
import BaseTabs from '../base/BaseTabs.vue';

defineProps({
  modelValue: { type: String, default: '' },
  contexts: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseTabs :items="contexts.map((context) => ({ id: context.id, label: context.label }))" :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" />
</template>
