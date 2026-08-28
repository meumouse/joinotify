<script setup>

/**
 * PhoneSenderList.vue frontend component.
 *
 * On the Cloud API transport each sender also carries the identifiers and the
 * health Meta assigns to it: the phone_number_id used as the message origin, the
 * quality rating and the 24-hour messaging limit.
 *
 * @since 1.4.7
 * @version 2.3.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import Tooltip from '../../../../components/tooltips/Tooltip.vue';

const props = defineProps({
  senders: { type: Array, default: () => [] },
  refreshingPhone: { type: String, default: '' },
  lastSync: { type: Number, default: 0 },
});

const isRefreshing = (phone) => props.refreshingPhone === phone;

const lastSyncLabel = computed(() => {
  if (!props.lastSync) {
    return '';
  }

  return new Date(props.lastSync * 1000).toLocaleString();
});

/**
 * Colour the quality pill the way Meta reports it.
 *
 * @since 2.3.0
 * @param {string} rating Quality rating reported by Meta.
 * @returns {string} Tailwind classes for the pill.
 */
function qualityClass(rating) {
  switch (String(rating || '').toUpperCase()) {
    case 'GREEN':
      return 'bg-emerald-100 text-emerald-600';
    case 'YELLOW':
      return 'bg-amber-100 text-amber-600';
    case 'RED':
      return 'bg-rose-100 text-rose-600';
    default:
      return 'bg-slate-100 text-slate-500';
  }
}

defineEmits(['remove', 'refresh']);
</script>

<template>
  <div class="w-[600px] space-y-6">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Registered senders', textDomain) }}</h3>
      <p class="mt-1 text-[13px] leading-5 text-slate-500">
        {{ __('Phone numbers already validated and available for use in flows.', textDomain) }}
      </p>
      <p v-if="lastSyncLabel" class="mt-1 text-xs text-slate-400">
        {{ __('Last synced:', textDomain) }} {{ lastSyncLabel }}
      </p>
    </div>

    <div v-if="!senders.length" class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-5 text-[14px] text-slate-500">
      {{ __('No number imported yet. Sync to bring in the numbers connected on your Joinotify account.', textDomain) }}
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="sender in senders"
        :key="sender.phone"
        class="flex flex-wrap items-center gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4"
      >
        <div class="min-w-[220px] flex-1">
          <div class="text-[14px] font-semibold text-slate-700">{{ sender.formatted || sender.phone }}</div>

          <template>
            <div v-if="sender.verified_name" class="mt-0.5 text-[13px] text-slate-500">{{ sender.verified_name }}</div>
            <div v-if="sender.phone_number_id" class="mt-0.5 font-mono text-xs text-slate-400">{{ sender.phone_number_id }}</div>
            <div v-if="sender.messaging_limit" class="mt-0.5 text-xs text-slate-400">
              {{ __('24h limit:', textDomain) }} {{ sender.messaging_limit }}
            </div>
          </template>
        </div>

        <span
          v-if="sender.quality_rating"
          class="rounded-full px-3 py-2 text-[13px] font-semibold"
          :class="qualityClass(sender.quality_rating)"
        >{{ sender.quality_rating }}</span>

        <Tooltip
          :content="__('Re-import from Joinotify', textDomain)"
          placement="top"
          :disabled="isRefreshing(sender.phone)"
        >
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
