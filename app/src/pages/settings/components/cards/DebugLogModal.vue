<script setup>

/**
 * DebugLogModal.vue frontend component.
 *
 * Renders the structured debug log entries (level, channel, message, context,
 * source) recorded in the dedicated table, with client-side filtering over the
 * most recent entries. Falls back to the flat text lines when only the legacy
 * payload is available.
 *
 * @since 1.4.7
 * @version 2.0.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import BaseListboxSelect from '../../../../components/base/BaseListboxSelect.vue';
import BaseSearchInput from '../../../../components/base/BaseSearchInput.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
  logs: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['close', 'update-logs', 'clear']);

const search = ref('');
const levelFilter = ref('');
const channelFilter = ref('');
const expanded = ref(new Set());

const LEVEL_BADGES = {
  debug: 'bg-slate-100 text-slate-600',
  info: 'bg-sky-100 text-sky-700',
  notice: 'bg-indigo-100 text-indigo-700',
  warning: 'bg-amber-100 text-amber-700',
  error: 'bg-red-100 text-red-700',
  critical: 'bg-rose-200 text-rose-800',
};

const availableChannels = computed(() => {
  const set = new Set();
  props.items.forEach((item) => item.channel && set.add(item.channel));
  return Array.from(set).sort();
});

const levelOptions = [
  { value: 'debug', label: __('Debug', textDomain) },
  { value: 'info', label: __('Info', textDomain) },
  { value: 'notice', label: __('Notice', textDomain) },
  { value: 'warning', label: __('Warning', textDomain) },
  { value: 'error', label: __('Error', textDomain) },
  { value: 'critical', label: __('Critical', textDomain) },
];

const channelOptions = computed(() =>
  availableChannels.value.map((channel) => ({ value: channel, label: channel }))
);

const filteredItems = computed(() => {
  const term = search.value.trim().toLowerCase();

  return props.items.filter((item) => {
    if (levelFilter.value && item.level !== levelFilter.value) {
      return false;
    }
    if (channelFilter.value && item.channel !== channelFilter.value) {
      return false;
    }
    if (term) {
      const haystack = `${item.message} ${item.context} ${item.hook} ${item.code} ${item.source}`.toLowerCase();
      if (!haystack.includes(term)) {
        return false;
      }
    }
    return true;
  });
});

const hasItems = computed(() => props.items.length > 0);

function badgeClass(level) {
  return LEVEL_BADGES[level] || LEVEL_BADGES.info;
}

function toggle(id) {
  const next = new Set(expanded.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  expanded.value = next;
}

function isExpanded(id) {
  return expanded.value.has(id);
}
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

      <div v-else-if="!hasItems" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-muted">
        {{ __('No logs available.', textDomain) }}
      </div>

      <template v-else>
        <div class="flex flex-wrap items-end gap-2">
          <BaseSearchInput
            v-model="search"
            :placeholder="__('Search message, context or hook…', textDomain)"
            class="min-w-[14rem] flex-1"
          />
          <BaseListboxSelect
            v-model="levelFilter"
            :options="levelOptions"
            :placeholder="__('All levels', textDomain)"
            class="w-44"
          />
          <BaseListboxSelect
            v-if="availableChannels.length"
            v-model="channelFilter"
            :options="channelOptions"
            :placeholder="__('All channels', textDomain)"
            class="w-44"
          />
        </div>

        <div class="max-h-[34rem] overflow-auto rounded-lg border border-slate-200">
          <table class="w-full table-fixed border-collapse text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="w-40 px-3 py-2 font-medium">{{ __('Date', textDomain) }}</th>
                <th class="w-24 px-3 py-2 font-medium">{{ __('Level', textDomain) }}</th>
                <th class="w-28 px-3 py-2 font-medium">{{ __('Channel', textDomain) }}</th>
                <th class="px-3 py-2 font-medium">{{ __('Message', textDomain) }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="item in filteredItems" :key="item.id">
                <tr
                  class="cursor-pointer border-t border-slate-100 align-top hover:bg-slate-50"
                  @click="toggle(item.id)"
                >
                  <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ item.created_at }}</td>
                  <td class="px-3 py-2">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase" :class="badgeClass(item.level)">
                      {{ item.level }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-xs text-slate-600">{{ item.channel }}</td>
                  <td class="px-3 py-2">
                    <div class="break-words text-slate-800">{{ item.message }}</div>
                    <div v-if="item.response_code || item.code" class="mt-1 text-xs text-slate-400">
                      <span v-if="item.response_code">HTTP {{ item.response_code }}</span>
                      <span v-if="item.code" class="ml-2">{{ item.code }}</span>
                    </div>
                  </td>
                </tr>
                <tr v-if="isExpanded(item.id)" class="border-t border-slate-100 bg-slate-950">
                  <td colspan="4" class="px-3 py-3">
                    <dl class="grid gap-2 text-xs text-slate-100">
                      <div v-if="item.hook" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-slate-400">{{ __('Hook', textDomain) }}</dt>
                        <dd class="break-all font-mono">{{ item.hook }}</dd>
                      </div>
                      <div v-if="item.request_url" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-slate-400">{{ __('Request', textDomain) }}</dt>
                        <dd class="break-all font-mono">{{ item.request_url }}</dd>
                      </div>
                      <div v-if="item.source" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-slate-400">{{ __('Source', textDomain) }}</dt>
                        <dd class="break-all font-mono">{{ item.source }}</dd>
                      </div>
                      <div v-if="item.context" class="flex gap-2">
                        <dt class="w-24 shrink-0 text-slate-400">{{ __('Context', textDomain) }}</dt>
                        <dd class="min-w-0 flex-1">
                          <pre class="overflow-auto whitespace-pre-wrap break-words font-mono text-slate-200">{{ item.context }}</pre>
                        </dd>
                      </div>
                    </dl>
                  </td>
                </tr>
              </template>
              <tr v-if="!filteredItems.length">
                <td colspan="4" class="px-3 py-6 text-center text-sm text-muted">
                  {{ __('No entries match the current filters.', textDomain) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

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
