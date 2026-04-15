<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../../builder/actions/composables/useActionRegistry';
import { __, textDomain } from '../../utils/i18n';
import { resolveSvgMarkup } from '../../utils/icon';

const props = defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  condition: { type: String, default: '' },
  operator: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click']);

const registry = useActionRegistry();

const resolvedIconSvg = computed(() => {
  registry.revision.value;
  const definition = registry.get('condition');
  return resolveSvgMarkup(definition?.iconSvg, definition?.icon);
});

const summary = computed(() => {
  const pieces = [props.condition, props.operator].filter(Boolean);
  return pieces.length ? pieces.join(' / ') : __('Branch condition', textDomain);
});
</script>

<template>
  <button
    type="button"
    class="w-full rounded-[30px] border p-6 text-left transition"
    :class="active
      ? 'border-violet-500 bg-violet-950 text-white shadow-[0_20px_55px_rgba(76,29,149,0.24)] ring-1 ring-violet-200'
      : 'border-violet-200 bg-white text-slate-900 shadow-[0_1px_4px_rgba(15,23,42,0.03)] hover:border-violet-300 hover:shadow-[0_16px_36px_rgba(139,92,246,0.12)]'"
    @click="$emit('click')"
  >
    <div class="flex items-start gap-4">
      <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border" :class="active ? 'border-white/10 bg-white/10 text-white' : 'border-violet-100 bg-violet-100 text-violet-700'">
        <span
          v-if="resolvedIconSvg"
          class="flex h-6 w-6 items-center justify-center"
          v-html="resolvedIconSvg"
        />
        <span v-else class="text-sm font-semibold uppercase tracking-[0.2em]">IF</span>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.24em]" :class="active ? 'bg-white/10 text-violet-100' : 'bg-violet-100 text-violet-700'">
            {{ __('Condition', textDomain) }}
          </span>
          <span class="text-xs font-medium uppercase tracking-[0.18em]" :class="active ? 'text-violet-100' : 'text-slate-400'">
            {{ summary }}
          </span>
        </div>

        <h3 class="mt-2 text-base font-semibold leading-6">
          {{ title || __('Condition', textDomain) }}
        </h3>

        <p class="mt-2 text-sm leading-6" :class="active ? 'text-violet-100' : 'text-slate-500'">
          {{ description }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
          <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.22em]" :class="active ? 'border-white/15 bg-white/10 text-white/90' : 'border-violet-100 bg-violet-50 text-violet-700'">
            True
          </span>
          <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.22em]" :class="active ? 'border-white/15 bg-white/10 text-white/90' : 'border-violet-100 bg-violet-50 text-violet-700'">
            False
          </span>
        </div>
      </div>
    </div>
  </button>
</template>
