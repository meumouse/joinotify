<script setup>
import { __, textDomain } from '../../utils/i18n';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click', 'edit']);
</script>

<template>
  <button
    type="button"
    class="w-full rounded-[28px] border p-5 text-left transition"
    :class="active ? 'border-slate-900 bg-slate-950 text-white shadow-xl' : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm'"
    @click="$emit('click')"
  >
    <div class="flex items-start gap-4">
      <div class="flex h-12 w-12 items-center justify-center rounded-lg" :class="active ? 'bg-white/10' : 'bg-slate-100'">T</div>
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold uppercase tracking-[0.22em]" :class="active ? 'text-slate-300' : 'text-slate-500'">{{ __('Trigger', textDomain) }}</p>
        <h3 class="mt-1 text-base font-semibold">{{ title || __('Trigger', textDomain) }}</h3>
        <p class="mt-2 text-sm leading-6" :class="active ? 'text-slate-300' : 'text-slate-500'">{{ description || context + ' / ' + trigger }}</p>
      </div>
    </div>
  </button>
</template>
