<script setup>

/**
 * FinishStep.vue frontend component.
 *
 * Two ways out of the wizard: straight into the builder, or into the settings
 * screen. Both mark the wizard as finished before navigating.
 *
 * @since 2.3.0
 */
import { __, textDomain } from '../../../../utils/i18n';

defineProps({
  links: { type: Object, default: () => ({}) },
  connected: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});

defineEmits(['finish']);
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('Joinotify is configured. Build your first automation now, or open the settings to fine-tune integrations, senders and message history.', textDomain) }}
    </p>

    <p
      v-if="!connected"
      class="max-w-2xl rounded-[8px] border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] leading-5 text-amber-800"
      role="status"
    >
      {{ __('You skipped connecting a Joinotify account, so workflows will build and save but will not send anything yet. Add your API key in Settings → General whenever you are ready.', textDomain) }}
    </p>

    <div class="grid max-w-3xl gap-4 sm:grid-cols-2">
      <button
        type="button"
        class="rounded-[8px] border border-primary-200 bg-white p-6 text-left transition hover:border-primary-700 hover:shadow-soft disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="busy"
        @click="$emit('finish', links.builder_url)"
      >
        <span class="block text-[15px] font-semibold text-ink">{{ __('Create an automation', textDomain) }}</span>
        <span class="mt-2 block text-[13px] leading-5 text-slate-500">
          {{ __('Open the visual builder and wire a trigger to your first WhatsApp message.', textDomain) }}
        </span>
      </button>

      <button
        type="button"
        class="rounded-[8px] border border-slate-200 bg-white p-6 text-left transition hover:border-slate-400 hover:shadow-soft disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="busy"
        @click="$emit('finish', links.settings_url)"
      >
        <span class="block text-[15px] font-semibold text-ink">{{ __('Go to settings', textDomain) }}</span>
        <span class="mt-2 block text-[13px] leading-5 text-slate-500">
          {{ __('Review integrations, senders, history retention and the advanced options.', textDomain) }}
        </span>
      </button>
    </div>
  </div>
</template>
