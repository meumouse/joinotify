<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import type { ActionDefinition } from '../registry/types';

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
    class="w-full rounded-[28px] border p-5 text-left transition"
    :class="[
      toneClasses,
      active ? 'border-primary-700 shadow-[0_18px_50px_rgba(10,140,255,0.12)] ring-1 ring-primary-100' : 'shadow-[0_1px_4px_rgba(15,23,42,0.03)] hover:border-slate-300 hover:shadow-[0_14px_35px_rgba(15,23,42,0.08)]',
      resolvedDefinition?.action === 'snippet_php' ? 'text-white' : 'text-slate-900',
    ]"
  >
    <div class="flex items-start gap-4">
      <div
        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border text-sm font-semibold uppercase tracking-[0.2em]"
        :class="resolvedDefinition?.action === 'snippet_php'
          ? 'border-white/10 bg-white/5 text-white'
          : 'border-slate-100 bg-slate-50 text-slate-600'"
      >
        {{ (resolvedDefinition?.icon || title).slice(0, 1).toUpperCase() }}
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-base font-semibold leading-6" :class="resolvedDefinition?.action === 'snippet_php' ? 'text-white' : 'text-slate-900'">
            {{ title }}
          </h3>
          <span
            v-if="resolvedDefinition?.hasSettings"
            class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.2em]"
            :class="resolvedDefinition?.action === 'snippet_php' ? 'bg-white/10 text-white' : 'bg-primary-50 text-primary-700'"
          >
            settings
          </span>
          <span
            v-if="resolvedDefinition?.isExpansible"
            class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.2em]"
            :class="resolvedDefinition?.action === 'snippet_php' ? 'bg-white/10 text-white' : 'bg-violet-50 text-violet-700'"
          >
            branches
          </span>
        </div>

        <p class="mt-2 text-sm leading-6" :class="resolvedDefinition?.action === 'snippet_php' ? 'text-slate-200' : 'text-slate-500'">
          {{ description }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
          <span
            v-if="data.sender"
            class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em]"
            :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 bg-white/10 text-white' : 'border-slate-200 bg-slate-50 text-slate-600'"
          >
            From: {{ String(data.sender) }}
          </span>
          <span
            v-if="data.receiver"
            class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em]"
            :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 bg-white/10 text-white' : 'border-slate-200 bg-slate-50 text-slate-600'"
          >
            To: {{ String(data.receiver) }}
          </span>
          <span
            v-if="resolvedDefinition?.action"
            class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em]"
            :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 bg-white/10 text-white' : 'border-slate-200 bg-slate-50 text-slate-500'"
          >
            {{ resolvedDefinition.action }}
          </span>
        </div>
      </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
      <button
        type="button"
        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
        :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 text-white hover:bg-white/10' : 'border-slate-200 text-slate-700 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800'"
        @click="$emit('edit')"
      >
        Edit
      </button>
      <button
        type="button"
        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
        :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 text-white hover:bg-white/10' : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
        @click="$emit('duplicate')"
      >
        Duplicate
      </button>
      <button
        type="button"
        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
        :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 text-white hover:bg-white/10' : 'border-rose-200 text-rose-700 hover:bg-rose-50'"
        @click="$emit('delete')"
      >
        Delete
      </button>
      <button
        v-if="resolvedDefinition?.isExpansible"
        type="button"
        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
        :class="resolvedDefinition?.action === 'snippet_php' ? 'border-white/15 text-white hover:bg-white/10' : 'border-violet-200 text-violet-700 hover:bg-violet-50'"
        @click="$emit('expand')"
      >
        Expand
      </button>
    </div>
  </article>
</template>
