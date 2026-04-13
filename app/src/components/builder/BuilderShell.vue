<script setup>
import { computed } from 'vue';

defineProps({
  debugMode: { type: Boolean, default: false },
});

const shellStyle = computed(() => ({
  top: '0',
  height: '100vh',
}));
</script>

<template>
  <div class="fixed inset-x-0 bottom-0 z-[999] w-screen overflow-hidden bg-shell-50 text-slate-900" :style="shellStyle">
    <slot name="navbar" />
    <div class="flex h-[calc(100%-72px)] w-full gap-6 overflow-hidden px-4 py-4 lg:px-6">
      <aside class="hidden w-[340px] shrink-0 overflow-y-auto xl:block">
        <slot name="sidebar" />
      </aside>
      <main class="min-w-0 flex-1 overflow-y-auto">
        <slot name="main" />
      </main>
      <aside class="hidden w-[420px] shrink-0 overflow-y-auto 2xl:block">
        <slot name="panel" />
      </aside>
    </div>
    <slot name="overlays" />
  </div>
</template>
