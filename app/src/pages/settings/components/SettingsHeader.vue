<script setup>

/**
 * SettingsHeader.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../utils/i18n';
import AppIcon from '../../../components/icons/AppIcon.vue';

defineProps({
  docsUrl: { type: String, required: true },
});
</script>

<template>
  <header class="flex items-start gap-3">
    <div class="mt-1 h-12 w-12 shrink-0">
      <AppIcon name="brand" class="h-full w-full text-success" />
    </div>

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
