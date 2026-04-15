<script setup lang="ts">
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
