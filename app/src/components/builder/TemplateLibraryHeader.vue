<script setup>
/**
 * TemplateLibraryHeader.vue
 *
 * Static, centered heading block displayed at the top of the template library
 * screen. Presentation only; renders an eyebrow badge, a large localized title
 * inviting the user to start from a template, and a supporting subtitle.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
</script>

<template>
  <div class="mx-auto max-w-3xl text-center">
    <span
      class="inline-flex items-center gap-2 rounded-full bg-primary-50 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-primary-800 ring-1 ring-inset ring-primary-100"
    >
      <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z" />
      </svg>
      {{ __('Automation templates', textDomain) }}
    </span>

    <h2 class="mt-6 text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl lg:text-[42px]">
      {{ __('Start automating your site from a template', textDomain) }}
    </h2>

    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-slate-500 sm:text-lg">
      {{ __('Choose a ready-made flow, import it with one click, and adjust it your way. Search by categories or names.', textDomain) }}
    </p>
  </div>
</template>
