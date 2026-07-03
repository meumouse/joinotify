<script setup lang="ts">
/**
 * BaseAlert.vue
 *
 * Themed inline notice box used to surface contextual messages in the builder.
 * Supports several tones (info, success, warning, danger, neutral) that map to
 * distinct color schemes, plus an optional title, message and default slot.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  tone: { type: String, default: 'info' },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
});

const toneMap: Record<string, string> = {
  info: 'border-sky-200 bg-sky-50 text-sky-900',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
  danger: 'border-rose-200 bg-rose-50 text-rose-900',
  neutral: 'border-slate-200 bg-slate-50 text-slate-900',
};

/**
 * Resolve the Tailwind class string for the current tone, falling back to the
 * info tone when an unknown tone is provided.
 *
 * @since 2.0.0
 * @returns {string} Tailwind utility classes for the alert container.
 */
const classes = computed(() => toneMap[props.tone] || toneMap.info);
</script>

<template>
  <div class="rounded-2xl border p-4 text-sm leading-6" :class="classes">
    <h4 v-if="title" class="text-sm font-semibold">{{ title }}</h4>
    <p v-if="message" :class="title ? 'mt-1' : ''">
      {{ message }}
    </p>
    <slot />
  </div>
</template>
