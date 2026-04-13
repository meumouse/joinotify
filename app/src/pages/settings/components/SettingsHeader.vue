<script setup>

/**
 * SettingsHeader.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../utils/i18n';
import BrandMark from '../../../components/brand/BrandMark.vue';

defineProps({
  docsUrl: { type: String, required: true },
});
</script>

<template>
  <header class="flex items-start gap-3">
    <BrandMark class="mt-1" size="md" variant="primary" />

    <div class="pt-1">
      <h1 class="text-[26px] font-normal leading-9 text-slate-800">
        {{ __('Joinotify: Automate your notifications. Simplify your communication.', textDomain) }}
      </h1>
      <p class="mt-6 max-w-[1180px] text-[16px] leading-6 text-slate-600">
        {{ __('Increase customer satisfaction by automating WhatsApp messaging with Joinotify. If you need help setting it up, visit our ', textDomain) }}
        <a class="font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">
          {{ __('Help Center', textDomain) }}
        </a>
      </p>
    </div>
  </header>
</template>
