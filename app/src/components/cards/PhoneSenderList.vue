<script setup>
import { __, textDomain } from '../../lib/i18n';
import Tooltip from '../base/Tooltip.vue';

const props = defineProps({
  senders: { type: Array, default: () => [] },
  refreshingPhone: { type: String, default: '' },
});

const isRefreshing = (phone) => props.refreshingPhone === phone;

defineEmits(['remove', 'refresh']);
</script>

<template>
  <div class="w-[600px] space-y-6">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Registered senders', textDomain) }}</h3>
      <p class="mt-1 text-[13px] leading-5 text-slate-500">
        {{ __('Phone numbers already validated and available for use in flows.', textDomain) }}
      </p>
    </div>

    <div v-if="!senders.length" class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-5 text-[14px] text-slate-500">
      {{ __('No validated sender yet.', textDomain) }}
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="sender in senders"
        :key="sender.phone"
        class="flex flex-wrap items-center gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4"
      >
        <div class="min-w-[220px] flex-1">
          <div class="text-[14px] font-semibold text-slate-700">{{ sender.formatted || sender.phone }}</div>
        </div>

        <Tooltip :content="__('Refresh connection', textDomain)" placement="top" :disabled="isRefreshing(sender.phone)">
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
            :disabled="isRefreshing(sender.phone)"
            :aria-busy="isRefreshing(sender.phone) ? 'true' : 'false'"
            :aria-label="isRefreshing(sender.phone) ? __('Refreshing connection', textDomain) : __('Refresh connection', textDomain)"
            @click="$emit('refresh', sender.phone)"
          >
            <span v-if="isRefreshing(sender.phone)" class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
            <span v-else aria-hidden="true">↻</span>
          </button>
        </Tooltip>

        <span
          class="rounded-full px-3 py-2 text-[13px] font-semibold"
          :class="sender.connection === 'connected' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500'"
        >
          {{ sender.connection === 'connected' ? __('Connected', textDomain) : __('Disconnected', textDomain) }}
        </span>

        <button
          type="button"
          class="rounded-[8px] border border-rose-200 px-4 py-2 text-[14px] font-medium text-rose-400 transition hover:bg-rose-50"
          @click="$emit('remove', sender.phone)"
        >
          {{ __('Remove', textDomain) }}
        </button>
      </div>
    </div>
  </div>
</template>
