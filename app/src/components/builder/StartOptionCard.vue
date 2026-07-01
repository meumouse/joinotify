<script setup>
/**
 * StartOptionCard.vue
 *
 * Presentational card used on the builder start screen to offer a single way to
 * begin a workflow (e.g. from scratch or from a template). Renders an icon slot,
 * title, description and a call-to-action button that emits a click event.
 *
 * @since 2.0.0
 */
import BaseButton from '../base/BaseButton.vue';

defineProps({
  title: { type: String, required: true },
  description: { type: String, required: true },
  cta: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <article class="flex h-full flex-col items-center rounded-lg border border-slate-200 bg-white px-8 py-10 text-center transition hover:border-primary-200 lg:px-10 lg:py-12">
    <div class="mb-7 flex h-14 w-14 items-center justify-center rounded-lg border border-dashed border-primary-200 bg-primary-50 text-primary-700">
      <slot name="icon" />
    </div>
    <h3 class="text-[20px] font-semibold tracking-tight text-slate-900">{{ title }}</h3>
    <p class="mt-3 max-w-[20rem] text-sm leading-6 text-slate-500">{{ description }}</p>
    <div class="mt-7">
      <BaseButton class="min-w-[148px]" :title="cta" variant="secondary" :disabled="disabled" @click="$emit('click')" />
    </div>
  </article>
</template>
