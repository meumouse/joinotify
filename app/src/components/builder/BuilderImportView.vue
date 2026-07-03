<script setup>
/**
 * BuilderImportView.vue
 *
 * Full-page import view where the user can paste exported JSON or drop a .json
 * file to load a real Joinotify export. It emits update/import/file/back events
 * so the parent can manage the JSON text and import flow.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';
import BaseFileDropzone from '../base/BaseFileDropzone.vue';
import BaseTextarea from '../base/BaseTextarea.vue';

defineProps({
  jsonText: { type: String, default: '' },
  importing: { type: Boolean, default: false },
});

defineEmits(['update:jsonText', 'import', 'file', 'back', 'error']);
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
    <div class="max-w-2xl">
      <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Import', textDomain) }}</p>
      <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ __('Import a real Joinotify export', textDomain) }}</h2>
      <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('The parser accepts plugin_version, post and workflow_content and preserves extra fields during editing.', textDomain) }}</p>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_340px]">
      <BaseTextarea :model-value="jsonText" rows="18" :placeholder="__('Paste the exported JSON here', textDomain)" @update:model-value="$emit('update:jsonText', $event)" />
      <BaseFileDropzone @file="$emit('file', $event)" @error="$emit('error', $event)">
        <p class="text-sm font-semibold text-slate-900">{{ __('Drag and drop a .json file', textDomain) }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Or click to choose the exported Joinotify file.', textDomain) }}</p>
      </BaseFileDropzone>
    </div>

    <div class="mt-4 flex gap-2">
      <BaseButton :title="__('Import', textDomain)" :loading="importing" @click="$emit('import')" />
      <BaseButton :title="__('Back', textDomain)" variant="secondary" @click="$emit('back')" />
    </div>
  </div>
</template>
