<script setup lang="ts">
import { computed } from 'vue';
import type { ActionDefinition } from '../registry/types';

const props = defineProps<{
  action: ActionDefinition;
  active?: boolean;
  compact?: boolean;
}>();

defineEmits(['click']);

const accentClasses = computed(() => (props.active
  ? 'border-primary-700 bg-primary-50 shadow-[0_18px_45px_rgba(10,140,255,0.14)]'
  : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.08)]'));

function glyph(value: string): string {
  const trimmed = String(value || '').trim();
  return trimmed ? trimmed.slice(0, 1).toUpperCase() : 'A';
}
</script>

<template>
  <button
    type="button"
    class="w-full rounded-[24px] border p-4 text-left transition"
    :class="[accentClasses, compact ? 'p-3' : 'p-4']"
    @click="$emit('click', action.action)"
  >
    <div class="flex items-start gap-3">
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm font-semibold uppercase tracking-[0.18em] text-slate-600">
        {{ glyph(action.icon || action.title) }}
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-sm font-semibold leading-6 text-slate-900">
            {{ action.title }}
          </h3>
          <span
            v-if="action.hasSettings"
            class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-primary-700"
          >
            settings
          </span>
          <span
            v-if="action.isExpansible"
            class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-violet-700"
          >
            branches
          </span>
        </div>

        <p class="mt-1 text-sm leading-6 text-slate-500">
          {{ action.description }}
        </p>
      </div>
    </div>
  </button>
</template>
