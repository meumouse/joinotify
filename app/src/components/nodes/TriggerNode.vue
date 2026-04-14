<script setup lang="ts">
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconSvg: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click', 'edit']);

const contextLabel = computed(() => {
  if (!props.context) {
    return __('Trigger', textDomain);
  }

  return props.context.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
});
</script>

<template>
  <button
    type="button"
    class="w-full rounded-[30px] border p-6 text-left transition"
    :class="active
      ? 'border-sky-500 bg-sky-950 text-white shadow-[0_20px_55px_rgba(2,6,23,0.24)] ring-1 ring-sky-200'
      : 'border-sky-200 bg-gradient-to-br from-white to-sky-50/60 text-slate-900 shadow-[0_1px_4px_rgba(15,23,42,0.03)] hover:border-sky-300 hover:shadow-[0_16px_36px_rgba(14,165,233,0.12)]'"
    @click="$emit('click')"
  >
    <div class="flex items-start gap-4">
      <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border" :class="active ? 'border-white/10 bg-white/10' : 'border-sky-100 bg-sky-100 text-sky-700'">
        <span
          v-if="iconSvg && String(iconSvg).trim().startsWith('<svg')"
          class="flex h-6 w-6 items-center justify-center"
          v-html="iconSvg"
        />
        <span v-else-if="icon" class="text-sm font-semibold uppercase tracking-[0.2em]">
          {{ icon.slice(0, 1).toUpperCase() }}
        </span>
        <span v-else class="text-sm font-semibold uppercase tracking-[0.2em]">
          T
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.24em]" :class="active ? 'bg-white/10 text-sky-100' : 'bg-sky-100 text-sky-700'">
            {{ __('Trigger', textDomain) }}
          </span>
          <span class="text-xs font-medium uppercase tracking-[0.18em]" :class="active ? 'text-sky-200' : 'text-slate-400'">
            {{ contextLabel }}
          </span>
        </div>

        <h3 class="mt-2 text-base font-semibold leading-6">
          {{ title || __('Trigger', textDomain) }}
        </h3>

        <p class="mt-2 text-sm leading-6" :class="active ? 'text-sky-100' : 'text-slate-500'">
          {{ description || context + (trigger ? ` / ${trigger}` : '') }}
        </p>
      </div>
    </div>
  </button>
</template>
