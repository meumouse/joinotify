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
});

defineEmits(['close', 'clear']);

const formattedLogs = computed(() => props.logs.join('\n'));
</script>

<template>
  <ModalDialog :open="open" :title="__('Debug logs', textDomain)" :description="__('View the recent events recorded by the plugin.', textDomain)" :eyebrow="__('Diagnostics', textDomain)" @close="$emit('close')">
    <div class="space-y-4">
      <div v-if="!logs.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-muted">
        {{ __('No logs available.', textDomain) }}
      </div>
      <pre v-else class="max-h-[28rem] overflow-auto rounded-2xl bg-slate-950 p-4 text-sm leading-6 text-slate-100">{{ formattedLogs }}</pre>

      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          @click="$emit('close')"
        >
          {{ __('Close', textDomain) }}
        </button>
        <button
          type="button"
          class="rounded-full bg-shell-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-shell-700"
          @click="$emit('clear')"
        >
          {{ __('Clear logs', textDomain) }}
        </button>
      </div>
    </div>
  </ModalDialog>
</template>
