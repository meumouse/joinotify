<script setup>
import { Teleport } from 'vue';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
});

defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[90] flex justify-end bg-slate-950/35">
      <button type="button" class="absolute inset-0" aria-label="Close drawer" @click="$emit('close')" />
      <aside class="relative z-10 h-full w-full max-w-[460px] overflow-y-auto border-l border-slate-200 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
          <button type="button" class="rounded-[8px] px-3 py-1.5 text-sm text-slate-500 hover:bg-primary-50 hover:text-primary-700" @click="$emit('close')">
            Close
          </button>
        </div>
        <div class="p-5">
          <slot />
        </div>
      </aside>
    </div>
  </Teleport>
</template>
