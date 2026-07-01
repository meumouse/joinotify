<script setup>
/**
 * TriggerIntegrationTabs.vue
 *
 * Tab bar for switching between integration contexts on the trigger setup
 * screen. Maps the contexts prop into BaseTabs items and forwards the selected
 * context id through v-model.
 *
 * @since 2.0.0
 */
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
