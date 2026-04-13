<script setup>
import BaseSearchInput from '../base/BaseSearchInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseSearchInput :model-value="modelValue" placeholder="Search templates" @update:model-value="$emit('update:modelValue', $event)" />
</template>
