<script setup>
import { __, textDomain } from '../../utils/i18n';
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  confirmLabel: { type: String, default: () => __('Confirm', textDomain) },
  cancelLabel: { type: String, default: () => __('Cancel', textDomain) },
  loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[9999] flex items-center justify-center px-4">
      <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="$emit('cancel')" />
      <div class="relative w-full max-w-lg rounded-[8px] bg-white p-6 shadow-soft">
        <h2 class="text-xl font-semibold text-ink">{{ title }}</h2>
        <p class="mt-2 text-sm leading-6 text-shell-500">{{ description }}</p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
          <button
            type="button"
            class="rounded-[8px] border border-slate-200 px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-slate-50"
            :disabled="loading"
            @click="$emit('cancel')"
          >
            {{ cancelLabel }}
          </button>
          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-[8px] bg-primary-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading"
            @click="$emit('confirm')"
          >
            <span
              v-if="loading"
              class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
            />
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</template>
