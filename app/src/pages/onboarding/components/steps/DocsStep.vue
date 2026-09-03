<script setup>

/**
 * DocsStep.vue frontend component.
 *
 * Points at the documentation once, at the moment it is most likely to be
 * needed, so the user knows where to look later.
 *
 * @since 2.3.0
 */
import { __, textDomain } from '../../../../utils/i18n';
import BaseButton from '../../../../components/base/BaseButton.vue';

defineProps({
  docsUrl: { type: String, default: '' },
});

const topics = [
  __('Building your first workflow: triggers, conditions and actions', textDomain),
  __('Connecting WooCommerce, WPForms, Elementor and the other integrations', textDomain),
  __('Message templates and what the WhatsApp 24-hour window means for you', textDomain),
  __('Placeholders, custom variables and the PHP extension API', textDomain),
];
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('Everything Joinotify does is documented, from the first workflow to the PHP hooks you can extend it with. Keep this link handy.', textDomain) }}
    </p>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
        {{ __('Documentation', textDomain) }}
      </p>
      <h3 class="mt-2 text-lg font-semibold text-ink">{{ __('Joinotify help center', textDomain) }}</h3>

      <ul class="mt-5 space-y-3 text-sm leading-6 text-slate-600">
        <li v-for="topic in topics" :key="topic" class="flex gap-3">
          <span aria-hidden="true" class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-700" />
          <span>{{ topic }}</span>
        </li>
      </ul>

      <div class="mt-6">
        <BaseButton
          :title="__('Open the documentation', textDomain)"
          :href="docsUrl"
          target="_blank"
        />
      </div>
    </div>
  </div>
</template>
