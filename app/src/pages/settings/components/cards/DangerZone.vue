<script setup>

/**
 * DangerZone.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../../utils/i18n';
import StatusBadge from '../../../../components/cards/StatusBadge.vue';

defineEmits(['reset', 'clear-logs']);
</script>

<template>
  <div class="rounded-lg border border-rose-200 bg-rose-50/80 p-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h3 class="text-base font-semibold text-rose-900">{{ __('Danger zone', textDomain) }}</h3>
        <p class="mt-2 text-sm leading-6 text-rose-700">
          {{ __('Irreversible actions for clearing configuration and logs.', textDomain) }}
        </p>
      </div>
      <StatusBadge :label="__('Irreversible', textDomain)" tone="danger" />
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      <button
        type="button"
        class="rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-500"
        @click="$emit('reset')"
      >
        {{ __('Reset settings', textDomain) }}
      </button>
      <button
        type="button"
        class="rounded-full border border-rose-200 bg-white px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50"
        @click="$emit('clear-logs')"
      >
        {{ __('Clear logs', textDomain) }}
      </button>
    </div>
  </div>
</template>
