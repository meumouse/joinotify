<script setup>
import { Teleport } from 'vue';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  sizeClass: { type: String, default: 'max-w-xl' },
});

defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[999] flex items-center justify-center px-4 py-6">
      <button type="button" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-label="Close dialog" @click="$emit('close')" />
      <div class="relative z-10 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl" :class="sizeClass">
        <header class="border-b border-slate-200 px-6 py-4">
          <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
        </header>
        <div class="p-6">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
