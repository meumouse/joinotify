<script setup>
import { __, textDomain } from '../../utils/i18n';

defineProps({
  label: { type: String, default: () => __('Add node', textDomain) },
});

defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-2 rounded-full border border-dashed border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-400 hover:bg-slate-50"
    @click="$emit('click')"
  >
    <span class="text-base leading-none">+</span>
    <span>{{ label }}</span>
  </button>
</template>
