<script setup>
import { ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const emit = defineEmits(['file']);
const inputRef = ref(null);

function handleDrop(event) {
  event.preventDefault();
  const file = event.dataTransfer?.files?.[0];
  if (file) {
    emit('file', file);
  }
}

function handlePick(event) {
  const file = event.target.files?.[0];
  if (file) {
    emit('file', file);
  }
}
</script>

<template>
  <div
    class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center transition hover:border-primary-300 hover:bg-white"
    @dragover.prevent
    @drop="handleDrop"
  >
    <input ref="inputRef" type="file" class="hidden" accept=".json,application/json" @change="handlePick" />
    <slot />
    <button type="button" class="mt-4 rounded-[8px] bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800" @click="inputRef?.click()">
      {{ __('Select file', textDomain) }}
    </button>
  </div>
</template>
