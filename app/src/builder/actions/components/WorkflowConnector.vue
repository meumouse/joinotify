<script setup lang="ts">
defineProps({
  label: { type: String, default: '' },
  branchKey: { type: String, default: '' },
  compact: { type: Boolean, default: false },
});
</script>

<template>
  <div class="flex w-full flex-col items-center py-2">
    <div v-if="label" class="mb-2 inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-500 shadow-[0_1px_4px_rgba(15,23,42,0.03)]">
      {{ label }}
      <span v-if="branchKey" class="ml-2 text-slate-400">{{ branchKey }}</span>
    </div>
    <div class="h-4 w-px bg-slate-300" :class="compact ? 'opacity-60' : ''" />
  </div>
</template>
