<script setup>
defineProps({
  modelValue: { type: String, default: 'all' },
  options: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <select class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10" :value="modelValue" @change="$emit('update:modelValue', $event.target.value)">
    <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
  </select>
</template>
