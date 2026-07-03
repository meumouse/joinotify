<script setup>

/**
 * ConfirmDialog.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../utils/i18n';
import ModalDialog from './ModalDialog.vue';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
  <ModalDialog :open="open" :title="title" :description="description" :eyebrow="__('Confirmation', textDomain)" @close="$emit('cancel')">
    <div class="flex items-center justify-end gap-3">
      <button
        type="button"
        class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        :disabled="loading"
        @click="$emit('cancel')"
      >
        {{ __('Cancel', textDomain) }}
      </button>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:bg-rose-400"
        :disabled="loading"
        @click="$emit('confirm')"
      >
        <span v-if="loading" class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
        {{ __('Confirm', textDomain) }}
      </button>
    </div>
  </ModalDialog>
</template>
