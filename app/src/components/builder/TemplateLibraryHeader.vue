<script setup>
/**
 * TemplateLibraryHeader.vue
 *
 * Static, centered heading and subtitle displayed at the top of the template
 * library screen. Presentation only; renders localized copy inviting the user
 * to start from a template.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
</script>

<template>
  <div class="mx-auto max-w-3xl text-center">
    <h2 class="text-[28px] font-semibold tracking-tight text-slate-700 sm:text-[32px] lg:text-[34px]">
      {{ __('Start automating your site from a template', textDomain) }}
    </h2>
    <p class="mt-3 text-base leading-7 text-slate-500">
      {{ __('Search by categories or names.', textDomain) }}
    </p>
  </div>
</template>
