<script setup>

/**
 * AiStep.vue frontend component.
 *
 * Informational: since WordPress 7.0 the AI provider and its credentials belong
 * to the site, configured once in Settings → Connectors, so the wizard only
 * reports the current state and links there. Skipping leaves the AI actions in
 * the builder untouched — they simply stay unconfigured.
 *
 * @since 2.3.0
 */
import { __, textDomain } from '../../../../utils/i18n';

defineProps({
  supported: { type: Boolean, default: false },
  configured: { type: Boolean, default: false },
  connectorsUrl: { type: String, default: '' },
});
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('Joinotify can use a language model to write message copy and suggest whole workflows. This is optional and you can set it up later — the plugin works fully without it.', textDomain) }}
    </p>

    <div class="max-w-2xl space-y-4">
      <div
        class="rounded-xl border px-4 py-3 text-[14px] leading-6"
        :class="configured ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-700'"
      >
        <p v-if="configured" class="font-medium">
          {{ __('WordPress already has an AI provider set up. The AI actions are ready to use.', textDomain) }}
        </p>

        <p v-else-if="supported" class="font-medium">
          {{ __('No AI provider is set up in WordPress yet. The AI actions stay unavailable until one is.', textDomain) }}
        </p>

        <p v-else class="font-medium">
          {{ __('AI features are turned off in this WordPress installation.', textDomain) }}
        </p>
      </div>

      <p class="text-[13px] leading-5 text-shell-500">
        {{ __('The provider and its API key are stored by WordPress, not by Joinotify, so every plugin on the site shares the same setup.', textDomain) }}
        <a
          v-if="connectorsUrl"
          class="font-semibold text-primary-700 underline underline-offset-4"
          :href="connectorsUrl"
        >{{ __('Open Settings → Connectors', textDomain) }}</a>
      </p>
    </div>
  </div>
</template>
