<script setup lang="ts">
import ActionLibraryPanel from '../../builder/actions/components/ActionLibraryPanel.vue';

defineProps({
  actions: { type: Array, default: () => [] },
  actionsLoading: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  open: { type: Boolean, default: false },
  context: { type: String, default: '' },
});

defineEmits(['close', 'select']);
</script>

<template>
  <div class="pointer-events-none absolute inset-y-0 right-0 z-20 flex h-full">
    <div
      class="pointer-events-auto h-full transition-[transform,opacity] duration-300 ease-out"
      :class="open ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'"
    >
      <ActionLibraryPanel :context="context" :loading="loading" @close="$emit('close')" @select="$emit('select', $event)" />
    </div>
  </div>
</template>
