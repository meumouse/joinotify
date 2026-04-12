<script setup>
import { __, textDomain } from '../../utils/i18n';
import ModalDialog from './ModalDialog.vue';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
  <ModalDialog :open="open" :title="title" :description="description" :eyebrow="__('Confirmation', textDomain)" @close="$emit('cancel')">
    <div class="flex items-center justify-end gap-3">
      <button
        type="button"
        class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        @click="$emit('cancel')"
      >
        {{ __('Cancel', textDomain) }}
      </button>
      <button
        type="button"
        class="rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-500"
        @click="$emit('confirm')"
      >
        {{ __('Confirm', textDomain) }}
      </button>
    </div>
  </ModalDialog>
</template>
