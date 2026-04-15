<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import type { ActionDefinition } from '../registry/types';
import { resolveSvgMarkup } from '../../../utils/icon';

const props = defineProps<{
  action: string;
  data: Record<string, unknown>;
  definition?: ActionDefinition | null;
  active?: boolean;
  compact?: boolean;
}>();

defineEmits(['edit', 'delete', 'duplicate', 'expand']);

const registry = useActionRegistry();

const resolvedDefinition = computed(() => props.definition || registry.get(props.action));
const title = computed(() => String(props.data?.title || resolvedDefinition.value?.title || props.action || 'Action'));
const description = computed(() => {
  const definition = resolvedDefinition.value;

  if (definition?.buildDescription) {
    return definition.buildDescription(props.data || {});
  }

  return registry.description(props.action, props.data || {});
});
const toneClasses = computed(() => {
  const action = String(resolvedDefinition.value?.action || props.action || '');

  if (action === 'condition') {
    return 'border-violet-200 bg-violet-50/70';
  }

  if (action === 'time_delay') {
    return 'border-amber-200 bg-amber-50/70';
  }

  if (action === 'stop_funnel') {
    return 'border-rose-200 bg-rose-50/70';
  }

  if (action === 'snippet_php') {
    return 'border-slate-800 bg-slate-950 text-white';
  }

  return 'border-slate-200 bg-white';
});

function hasSvg(value: string): boolean {
  return String(value || '').trim().startsWith('<svg');
}

const resolvedIconSvg = computed(() => resolveSvgMarkup(resolvedDefinition.value?.iconSvg, resolvedDefinition.value?.icon));
</script>

<template>
  <component
    v-if="resolvedDefinition?.cardComponent"
    :is="resolvedDefinition.cardComponent"
    :action="action"
    :data="data"
    :definition="resolvedDefinition"
    :active="active"
    :compact="compact"
    @edit="$emit('edit')"
    @delete="$emit('delete')"
    @duplicate="$emit('duplicate')"
    @expand="$emit('expand')"
  />
  <article
    v-else
    class="relative w-full rounded-[8px] border border-slate-200 bg-white px-6 py-5 text-left transition"
    :class="[
      compact ? 'px-5 py-4' : 'px-6 py-5',
      active ? 'border-slate-300 shadow-[0_12px_28px_rgba(15,23,42,0.08)]' : 'shadow-[0_1px_4px_rgba(15,23,42,0.03)] hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.06)]',
      resolvedDefinition?.action === 'snippet_php' ? 'bg-slate-950 text-white' : 'text-slate-900',
    ]"
  >
    <div class="absolute right-3 top-3">
      <button
        type="button"
        class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
        :aria-label="`${title} actions`"
        @click.stop="$emit('edit')"
      >
        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
          <path d="M12 5.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 8.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 8.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
        </svg>
      </button>
    </div>

    <div class="flex items-start gap-4 pr-9">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[14px] border text-sm font-semibold uppercase tracking-[0.18em]"
        :class="resolvedDefinition?.action === 'snippet_php'
          ? 'border-white/10 bg-white/5 text-white'
          : 'border-slate-200 bg-slate-50 text-slate-600'"
      >
        <span
          v-if="hasSvg(resolvedIconSvg)"
          class="flex h-5 w-5 items-center justify-center"
          v-html="resolvedIconSvg"
        />
        <span v-else-if="resolvedDefinition?.icon" class="text-xs font-semibold uppercase tracking-[0.18em]">
          {{ String(resolvedDefinition.icon).slice(0, 1).toUpperCase() }}
        </span>
        <span v-else class="text-xs font-semibold uppercase tracking-[0.18em]">
          {{ String(title).slice(0, 1).toUpperCase() }}
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <h3 class="text-[15px] font-semibold leading-6" :class="resolvedDefinition?.action === 'snippet_php' ? 'text-white' : 'text-slate-900'">
          {{ title }}
        </h3>

        <p class="mt-1 text-[13px] leading-5" :class="resolvedDefinition?.action === 'snippet_php' ? 'text-slate-300' : 'text-slate-500'">
          {{ description }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-medium text-slate-500">
          <span
            v-if="data.sender"
            class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1"
          >
            Remetente: {{ String(data.sender) }}
          </span>
          <span
            v-if="data.receiver"
            class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1"
          >
            Destinatário: {{ String(data.receiver) }}
          </span>
          <span
            v-if="resolvedDefinition?.action"
            class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1"
          >
            {{ resolvedDefinition.action }}
          </span>
        </div>
      </div>
    </div>
  </article>
</template>
