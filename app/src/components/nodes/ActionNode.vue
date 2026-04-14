<script setup lang="ts">
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  action: { type: String, default: '' },
  badge: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconSvg: { type: String, default: '' },
  accent: { type: String, default: 'slate' },
  active: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

defineEmits(['click']);

const accentMap: Record<string, { ring: string; body: string; badge: string; icon: string; border: string }> = {
  slate: {
    ring: 'ring-slate-200',
    body: 'border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:shadow-[0_12px_30px_rgba(15,23,42,0.08)]',
    badge: 'bg-slate-100 text-slate-600',
    icon: 'bg-slate-100 text-slate-600',
    border: 'border-slate-200',
  },
  blue: {
    ring: 'ring-sky-200',
    body: 'border-sky-200 bg-white text-slate-900 hover:border-sky-300 hover:shadow-[0_12px_30px_rgba(14,165,233,0.12)]',
    badge: 'bg-sky-100 text-sky-700',
    icon: 'bg-sky-50 text-sky-700',
    border: 'border-sky-200',
  },
  amber: {
    ring: 'ring-amber-200',
    body: 'border-amber-200 bg-white text-slate-900 hover:border-amber-300 hover:shadow-[0_12px_30px_rgba(245,158,11,0.12)]',
    badge: 'bg-amber-100 text-amber-700',
    icon: 'bg-amber-50 text-amber-700',
    border: 'border-amber-200',
  },
  rose: {
    ring: 'ring-rose-200',
    body: 'border-rose-200 bg-white text-slate-900 hover:border-rose-300 hover:shadow-[0_12px_30px_rgba(244,63,94,0.12)]',
    badge: 'bg-rose-100 text-rose-700',
    icon: 'bg-rose-50 text-rose-700',
    border: 'border-rose-200',
  },
  emerald: {
    ring: 'ring-emerald-200',
    body: 'border-emerald-200 bg-white text-slate-900 hover:border-emerald-300 hover:shadow-[0_12px_30px_rgba(16,185,129,0.12)]',
    badge: 'bg-emerald-100 text-emerald-700',
    icon: 'bg-emerald-50 text-emerald-700',
    border: 'border-emerald-200',
  },
  violet: {
    ring: 'ring-violet-200',
    body: 'border-violet-200 bg-white text-slate-900 hover:border-violet-300 hover:shadow-[0_12px_30px_rgba(139,92,246,0.12)]',
    badge: 'bg-violet-100 text-violet-700',
    icon: 'bg-violet-50 text-violet-700',
    border: 'border-violet-200',
  },
};

const accent = computed(() => accentMap[props.accent] || accentMap.slate);

function firstGlyph(value: string): string {
  const trimmed = String(value || '').trim();
  return trimmed ? trimmed.slice(0, 1).toUpperCase() : 'A';
}
</script>

<template>
  <button
    type="button"
    class="w-full rounded-[28px] border p-5 text-left transition"
    :class="[
      accent.body,
      active ? `ring-1 ${accent.ring} shadow-[0_18px_45px_rgba(15,23,42,0.16)]` : 'shadow-[0_1px_4px_rgba(15,23,42,0.03)]',
      compact ? 'p-4' : 'p-5',
    ]"
    @click="$emit('click')"
  >
    <div class="flex items-start gap-4">
      <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl border" :class="[accent.border, accent.icon]">
        <span
          v-if="iconSvg && String(iconSvg).trim().startsWith('<svg')"
          class="flex h-6 w-6 items-center justify-center"
          v-html="iconSvg"
        />
        <span v-else-if="icon" class="text-xs font-semibold uppercase tracking-[0.22em]">
          {{ firstGlyph(icon) }}
        </span>
        <span v-else class="text-xs font-semibold uppercase tracking-[0.22em]">
          {{ firstGlyph(title || action || __('Action', textDomain)) }}
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.24em]" :class="accent.badge">
            {{ badge || __('Action', textDomain) }}
          </span>
          <span v-if="action" class="text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
            {{ action }}
          </span>
        </div>

        <h3 class="mt-2 text-sm font-semibold leading-6 text-slate-900">
          {{ title || action || __('Action', textDomain) }}
        </h3>

        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ description }}
        </p>
      </div>
    </div>
  </button>
</template>
