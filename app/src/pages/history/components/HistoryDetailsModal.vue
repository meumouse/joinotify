<script setup>
/**
 * HistoryDetailsModal.vue — full details for a single message history entry.
 *
 * A template send also shows the template it used: name, language, the value
 * given to each variable and the components payload that went to WhatsApp.
 * The footer can export the record as JSON.
 *
 * @since 2.0.0
 * @version 2.4.2
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  entry: { type: Object, default: () => ({}) },
  exporting: { type: Boolean, default: false },
  statusLabels: { type: Object, default: () => ({}) },
  sourceLabels: { type: Object, default: () => ({}) },
});

defineEmits(['close', 'export']);

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
    { label: __('Error', textDomain), value: entry.error_label || entry.error || '-' },
  ];
});

const template = computed(() => {
  const value = props.entry?.template;

  if (!value || typeof value !== 'object' || !value.name) {
    return null;
  }

  return {
    ...value,
    parameters: Array.isArray(value.parameters) ? value.parameters : [],
    components: Array.isArray(value.components) ? value.components : [],
  };
});

const componentLabels = computed(() => ({
  header: __('Header', textDomain),
  body: __('Body', textDomain),
  button: __('Button', textDomain),
}));

/**
 * Label the template component a parameter belongs to. Meta numbers buttons
 * from zero, so the button position is shown counted from one.
 *
 * @param {Record<string, unknown>} parameter Flattened template parameter.
 * @returns {string} Component label.
 */
function componentLabel(parameter) {
  const component = String(parameter.component || 'body');
  const label = componentLabels.value[component] || component;

  return component === 'button' ? `${label} #${Number(parameter.index || 0) + 1}` : label;
}

/**
 * Write a variable the way the template text shows it. Built here because the
 * braces would otherwise close the template interpolation.
 *
 * @param {unknown} key Variable name or position.
 * @returns {string} Variable token.
 */
function variableToken(key) {
  return `{{${String(key ?? '')}}}`;
}

const payloadJson = computed(() => (template.value ? JSON.stringify(template.value.components, null, 2) : ''));
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

          <div v-if="template" class="mt-4">
            <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Template', textDomain) }}</dt>
            <dd class="mt-1 overflow-hidden rounded-[8px] ring-1 ring-slate-100">
              <div class="flex flex-wrap items-center gap-2 px-4 py-3 text-[14px] text-slate-700">
                <span class="break-all font-mono">{{ template.name }}</span>
                <span v-if="template.language" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[12px] font-medium text-slate-600">{{ template.language }}</span>
                <span v-if="template.masked" class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[12px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ __('Values hidden', textDomain) }}</span>
              </div>

              <div v-if="template.parameters.length" class="overflow-x-auto border-t border-slate-100">
                <table class="w-full text-left text-[13px]">
                  <thead class="bg-slate-50 text-[12px] uppercase tracking-wide text-slate-400">
                    <tr>
                      <th class="whitespace-nowrap px-4 py-2 font-medium">{{ __('Component', textDomain) }}</th>
                      <th class="whitespace-nowrap px-4 py-2 font-medium">{{ __('Variable', textDomain) }}</th>
                      <th class="px-4 py-2 font-medium">{{ __('Value', textDomain) }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(parameter, index) in template.parameters" :key="index" class="border-t border-slate-100 align-top">
                      <td class="whitespace-nowrap px-4 py-2 text-slate-500">{{ componentLabel(parameter) }}</td>
                      <td class="whitespace-nowrap px-4 py-2 font-mono text-slate-600">{{ variableToken(parameter.key) }}</td>
                      <td class="whitespace-pre-wrap break-words px-4 py-2 text-slate-700">{{ parameter.value || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="border-t border-slate-100 px-4 py-3 text-[13px] text-slate-500">{{ __('This template was sent without variables.', textDomain) }}</p>

              <details v-if="template.components.length" class="border-t border-slate-100 px-4 py-3">
                <summary class="cursor-pointer text-[13px] font-medium text-slate-500">{{ __('Payload sent', textDomain) }}</summary>
                <pre class="mt-2 max-h-64 overflow-auto rounded-[6px] bg-slate-50 p-3 font-mono text-[12px] text-slate-700">{{ payloadJson }}</pre>
              </details>
            </dd>
          </div>

          <div class="mt-4">
            <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Content', textDomain) }}</dt>
            <dd class="mt-1 whitespace-pre-wrap break-words rounded-[8px] bg-slate-50 px-4 py-3 text-[14px] text-slate-700 ring-1 ring-slate-100">{{ entry.content || '—' }}</dd>
            <p v-if="template && template.rendered_from === 'fallback'" class="mt-1.5 text-[12px] text-slate-500">
              {{ __('The template text was not available when this message was sent, so its name and variables are shown instead.', textDomain) }}
            </p>
          </div>

          <div v-if="entry.media_url" class="mt-4">
            <dt class="text-[12px] font-medium uppercase tracking-wide text-slate-400">{{ __('Media URL', textDomain) }}</dt>
            <dd class="mt-0.5 break-all text-[14px]">
              <a :href="entry.media_url" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ entry.media_url }}</a>
            </dd>
          </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
          <button
            type="button"
            class="rounded-[8px] border border-slate-200 px-5 py-2.5 text-[14px] font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="exporting || !entry.id"
            @click="$emit('export')"
          >
            {{ exporting ? __('Exporting…', textDomain) : __('Export as JSON', textDomain) }}
          </button>
          <button type="button" class="rounded-[8px] bg-slate-100 px-5 py-2.5 text-[14px] font-semibold text-slate-700 transition hover:bg-slate-200" @click="$emit('close')">
            {{ __('Close', textDomain) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
