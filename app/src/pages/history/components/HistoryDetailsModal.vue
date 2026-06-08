<script setup>
/**
 * HistoryDetailsModal.vue — full details for a single message history entry.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  entry: { type: Object, default: () => ({}) },
  statusLabels: { type: Object, default: () => ({}) },
  sourceLabels: { type: Object, default: () => ({}) },
});

defineEmits(['close']);

const rows = computed(() => {
  const entry = props.entry || {};

  return [
    { label: __('Date', textDomain), value: entry.created_at || '-' },
    { label: __('Status', textDomain), value: props.statusLabels[entry.status] || entry.status || '-' },
    { label: __('Source', textDomain), value: props.sourceLabels[entry.source] || entry.source || '-' },
    { label: __('Sender', textDomain), value: entry.sender || '-' },
    { label: __('Recipient', textDomain), value: entry.receiver || '-' },
    { label: __('Type', textDomain), value: entry.message_type || '-' },
    { label: __('Media type', textDomain), value: entry.media_type || '-' },
    { label: __('Response code', textDomain), value: entry.response_code || '-' },
    { label: __('Attempts', textDomain), value: entry.attempts ?? '-' },
    { label: __('Error', textDomain), value: entry.error || '-' },
  ];
});
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[100000] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="$emit('close')"></div>

      <div class="relative z-10 w-full max-w-2xl overflow-hidden rounded-[10px] bg-white shadow-xl ring-1 ring-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <h2 class="text-[16px] font-semibold text-slate-800">{{ __('Message details', textDomain) }}</h2>
          <button type="button" class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="$emit('close')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor"><path d="M18.3 5.71 12 12.01l-6.3-6.3-1.41 1.41 6.3 6.3-6.3 6.3 1.41 1.41 6.3-6.3 6.3 6.3 1.41-1.41-6.3-6.3 6.3-6.3z"></path></svg>
          </button>
        </div>

        <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
          <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div v-for="row in rows" :key="row.label" class="flex flex-col">
              <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ row.label }}</dt>
              <dd class="mt-0.5 break-words text-[14px] text-slate-700">{{ row.value }}</dd>
            </div>
          </dl>

          <div v-if="entry.workflow_title" class="mt-4">
            <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Workflow', textDomain) }}</dt>
            <dd class="mt-0.5 text-[14px]">
              <a v-if="entry.workflow_edit_url" :href="entry.workflow_edit_url" class="text-primary-600 hover:underline">{{ entry.workflow_title }}</a>
              <span v-else class="text-slate-700">{{ entry.workflow_title }}</span>
            </dd>
          </div>

          <div class="mt-4">
            <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Content', textDomain) }}</dt>
            <dd class="mt-1 whitespace-pre-wrap break-words rounded-[8px] bg-slate-50 px-4 py-3 text-[14px] text-slate-700 ring-1 ring-slate-100">{{ entry.content || '—' }}</dd>
          </div>

          <div v-if="entry.media_url" class="mt-4">
            <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Media URL', textDomain) }}</dt>
            <dd class="mt-0.5 break-all text-[14px]">
              <a :href="entry.media_url" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ entry.media_url }}</a>
            </dd>
          </div>
        </div>

        <div class="flex justify-end border-t border-slate-100 px-6 py-4">
          <button type="button" class="rounded-[8px] bg-slate-100 px-5 py-2.5 text-[14px] font-semibold text-slate-700 transition hover:bg-slate-200" @click="$emit('close')">
            {{ __('Close', textDomain) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
