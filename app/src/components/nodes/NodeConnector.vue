<script setup lang="ts">
defineProps({
  branchLabel: { type: String, default: '' },
  compact: { type: Boolean, default: false },
});
</script>

<template>
  <div class="flex flex-col items-center py-3" :class="compact ? 'py-2' : ''">
    <div class="h-8 w-px bg-gradient-to-b from-slate-300 via-slate-300 to-transparent" />
    <div v-if="branchLabel" class="mt-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 shadow-[0_1px_4px_rgba(15,23,42,0.03)]">
      {{ branchLabel }}
    </div>
  </div>
</template>
