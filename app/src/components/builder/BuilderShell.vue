<script setup>
import { computed } from 'vue';

defineProps({
  debugMode: { type: Boolean, default: false },
  hideNavbar: { type: Boolean, default: false },
});

const shellStyle = computed(() => ({
  top: '0',
  height: '100vh',
}));
</script>

<template>
  <div
    class="fixed inset-x-0 bottom-0 z-[999] w-screen overflow-hidden bg-shell-50 text-slate-900"
    :class="debugMode ? 'builder-shell--debug' : ''"
    :style="shellStyle"
  >
    <slot v-if="!hideNavbar" name="navbar" />
    <div class="flex w-full min-h-0 overflow-hidden" :class="hideNavbar ? 'h-full' : 'h-[calc(100%-72px)]'">
      <main class="min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden">
        <slot name="main" />
      </main>
    </div>
    <slot name="overlays" />
  </div>
</template>

<style scoped>
.builder-shell--debug {
  margin-top: 32px;
}
</style>
