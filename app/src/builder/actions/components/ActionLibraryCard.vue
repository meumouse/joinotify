<script setup lang="ts">
import { computed } from 'vue';
import type { ActionDefinition } from '../registry/types';
import { resolveSvgMarkup } from '../../../utils/icon';

const props = defineProps<{
  action: ActionDefinition;
  active?: boolean;
  compact?: boolean;
}>();

defineEmits(['click']);

const accentClasses = computed(() => (props.active
  ? 'border-primary-700 bg-primary-50 shadow-[0_18px_45px_rgba(10,140,255,0.14)]'
  : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.08)]'));

function hasSvg(value: string): boolean {
  return String(value || '').trim().startsWith('<svg');
}

const resolvedIconSvg = computed(() => resolveSvgMarkup(props.action.iconSvg, props.action.icon));

/** Serialize the action as a vue-flow node type payload for drag-and-drop onto FlowCanvas */
function onDragStart(event: DragEvent) {
  if (!event.dataTransfer) return;

  const payload = {
    type: props.action.action,
    label: props.action.title,
    description: props.action.description,
    icon: props.action.icon ?? '',
    color: 'bg-primary-600',
    category: (Array.isArray(props.action.context) ? props.action.context[0] : props.action.context) ?? '',
  };

  event.dataTransfer.setData('application/vueflow-node-type', JSON.stringify(payload));
  event.dataTransfer.effectAllowed = 'move';
}
</script>

<template>
  <button
    type="button"
    draggable="true"
    class="w-full cursor-grab rounded-[14px] border p-4 text-left transition active:cursor-grabbing"
    :class="[accentClasses, compact ? 'p-3' : 'p-4']"
    @click="$emit('click', action)"
    @dragstart="onDragStart"
  >
    <div class="flex items-start gap-3.5">
      <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600">
        <span
          v-if="hasSvg(resolvedIconSvg)"
          class="flex h-5 w-5 items-center justify-center"
          v-html="resolvedIconSvg"
        />
        <span v-else-if="action.icon" class="text-sm font-semibold uppercase tracking-[0.18em]">
          {{ String(action.icon).slice(0, 1).toUpperCase() }}
        </span>
        <span v-else class="text-sm font-semibold uppercase tracking-[0.18em]">
          {{ String(action.title || 'A').slice(0, 1).toUpperCase() }}
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <h3 class="text-sm font-semibold leading-5 text-slate-900">
          {{ action.title }}
        </h3>
        <p class="mt-1 text-sm leading-5 text-slate-500">
          {{ action.description }}
        </p>

      </div>
    </div>
  </button>
</template>
