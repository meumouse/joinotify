<script setup>
import { __, textDomain } from '../../utils/i18n';
</script>

<template>
  <div>
    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-shell-500">{{ __('Templates', textDomain) }}</p>
    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ __('Start automating your site from a template', textDomain) }}</h2>
    <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('Search by categories or names', textDomain) }}</p>
  </div>
</template>
