<script setup>
/**
 * BaseFileDropzone.vue
 *
 * Drag-and-drop file input zone that also supports selecting a file through a
 * hidden native input. Accepts JSON files and emits the chosen file via the
 * "file" event, used for importing workflows in the builder.
 *
 * @since 2.0.0
 */
import { ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const emit = defineEmits(['file']);
const inputRef = ref(null);

/**
 * Handle a file dropped onto the zone, emitting the first dropped file.
 *
 * @since 2.0.0
 * @param {DragEvent} event The native drop event.
 */
function handleDrop(event) {
  event.preventDefault();
  const file = event.dataTransfer?.files?.[0];
  if (file) {
    emit('file', file);
  }
}

/**
 * Handle a file chosen through the native file picker, emitting the first file.
 *
 * @since 2.0.0
 * @param {Event} event The native change event from the file input.
 */
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
