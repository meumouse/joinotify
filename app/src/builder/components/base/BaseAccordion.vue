<script setup lang="ts">
defineProps({
  title: { type: String, default: '' },
  open: { type: Boolean, default: true },
});
</script>

<template>
  <details class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm" :open="open">
    <summary class="cursor-pointer list-none text-sm font-semibold text-slate-900">
      {{ title }}
    </summary>
    <div class="pt-4">
      <slot />
    </div>
  </details>
</template>
