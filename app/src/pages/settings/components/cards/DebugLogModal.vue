<script setup>

/**
 * DebugLogModal.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  logs: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['close', 'update-logs', 'clear']);

const formattedLogs = computed(() => props.logs.join('\n'));
</script>

<template>
  <ModalDialog
    :open="open"
    :title="__('Debug logs', textDomain)"
    :description="__('View the recent events recorded by the plugin.', textDomain)"
    :eyebrow="__('Diagnostics', textDomain)"
    size-class="max-w-5xl"
    @close="$emit('close')"
  >
    <div class="space-y-4">
      <div v-if="loading" class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-5">
        <div class="joinotify-skeleton h-4 w-44 rounded-full" />
        <div class="joinotify-skeleton h-4 w-full rounded-full" />
        <div class="joinotify-skeleton h-4 w-11/12 rounded-full" />
        <div class="joinotify-skeleton h-4 w-10/12 rounded-full" />
        <div class="joinotify-skeleton h-4 w-8/12 rounded-full" />
        <div class="joinotify-skeleton h-4 w-9/12 rounded-full" />
      </div>

      <div v-else-if="!logs.length" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-muted">
        {{ __('No logs available.', textDomain) }}
      </div>

      <pre v-else class="max-h-[34rem] overflow-auto rounded-lg bg-slate-950 p-4 text-sm leading-6 text-slate-100">{{ formattedLogs }}</pre>

      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-full bg-shell-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-shell-700 disabled:cursor-not-allowed disabled:opacity-70"
          :disabled="loading"
          @click="$emit('update-logs')"
        >
          <span v-if="loading" class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
          {{ __('Update logs', textDomain) }}
        </button>
        <button
          type="button"
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          @click="$emit('clear')"
        >
          {{ __('Clear logs', textDomain) }}
        </button>
      </div>
    </div>
  </ModalDialog>
</template>
