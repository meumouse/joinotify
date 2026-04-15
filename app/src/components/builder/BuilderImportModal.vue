<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';
import ModalDialog from '../modals/ModalDialog.vue';
import BaseFileDropzone from '../base/BaseFileDropzone.vue';

defineProps({
  open: { type: Boolean, default: false },
  importing: { type: Boolean, default: false },
  fileName: { type: String, default: '' },
  error: { type: String, default: '' },
});

defineEmits(['close', 'file', 'import']);
</script>

<template>
  <ModalDialog :open="open" :title="__('Import template', textDomain)" sizeClass="max-w-2xl" @close="$emit('close')">
    <div class="space-y-5">
      <p class="text-sm leading-6 text-slate-500">
        {{ __('Drag and drop the template JSON file or click to browse. The format must match a real Joinotify export.', textDomain) }}
      </p>

      <BaseFileDropzone @file="$emit('file', $event)">
        <p class="text-sm font-semibold text-slate-900">{{ __('Drag and drop the file here', textDomain) }}</p>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Or click to select the template file.', textDomain) }}</p>
        <p v-if="fileName" class="mt-3 text-sm font-medium text-slate-700">{{ __('Selected file:', textDomain) }} {{ fileName }}</p>
      </BaseFileDropzone>

      <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </p>

      <div class="flex items-center justify-end gap-3">
        <BaseButton :title="__('Cancel', textDomain)" variant="ghost" @click="$emit('close')" />
        <BaseButton :title="__('Import', textDomain)" :loading="importing" :disabled="!fileName" @click="$emit('import')" />
      </div>
    </div>
  </ModalDialog>
</template>
