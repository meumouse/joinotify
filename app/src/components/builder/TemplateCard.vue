<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  category: { type: String, default: '' },
  integration: { type: String, default: '' },
  trigger: { type: String, default: '' },
  available: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <article class="rounded-lg border border-slate-200 bg-white p-5 transition hover:border-primary-200 hover:shadow-soft">
    <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
    <p class="mt-2 text-sm leading-6 text-slate-500">{{ description }}</p>
    <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
      <span class="rounded-full bg-primary-50 px-2.5 py-1 text-primary-700">{{ category || __('No category', textDomain) }}</span>
      <span class="rounded-full bg-primary-50 px-2.5 py-1 text-primary-700">{{ integration || __('No integration', textDomain) }}</span>
      <span class="rounded-full bg-primary-50 px-2.5 py-1 text-primary-700">{{ trigger || __('No trigger', textDomain) }}</span>
    </div>
    <div class="mt-5">
      <BaseButton class="w-full" :title="__('Import workflow', textDomain)" variant="secondary" :disabled="!available" @click="$emit('click')" />
    </div>
  </article>
</template>
