<script setup lang="ts">
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  label: { type: String, default: '' },
  compact: { type: Boolean, default: false },
});

defineEmits(['click']);

const resolvedLabel = computed(() => props.label || __('Add node', textDomain));
</script>

<template>
  <button
    type="button"
    class="group inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-[0_1px_6px_rgba(15,23,42,0.06)] transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
    :class="compact ? 'px-3 py-2 text-xs' : ''"
    @click="$emit('click')"
  >
    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-sm leading-none text-slate-600 transition group-hover:bg-slate-200">
      +
    </span>
    <span>{{ resolvedLabel }}</span>
  </button>
</template>
