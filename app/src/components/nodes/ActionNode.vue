<script setup>
import { __, textDomain } from '../../utils/i18n';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  action: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="w-full rounded-[24px] border p-4 text-left transition"
    :class="active ? 'border-slate-900 bg-slate-950 text-white shadow-xl' : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm'"
    @click="$emit('click')"
  >
    <div class="flex items-start gap-3">
      <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="active ? 'bg-white/10' : 'bg-slate-100'">A</div>
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold uppercase tracking-[0.22em]" :class="active ? 'text-slate-300' : 'text-slate-500'">{{ __('Action', textDomain) }}</p>
        <h3 class="mt-1 text-sm font-semibold">{{ title || action }}</h3>
        <p class="mt-2 text-sm leading-6" :class="active ? 'text-slate-300' : 'text-slate-500'">{{ description }}</p>
      </div>
    </div>
  </button>
</template>
