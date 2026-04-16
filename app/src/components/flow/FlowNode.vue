<script setup lang="ts">
/**
 * FlowNode.vue
 *
 * Custom node used in the vue-flow canvas.
 *
 * @since 1.4.7
 */
import { computed, ref } from 'vue';
import { DotsVerticalRounded } from '@boxicons/vue';
import { Handle, Position } from '@vue-flow/core';
import { onClickOutside } from '@vueuse/core';
import { getFlowNodeConfig } from './flowNodeTypes';
import { resolveSvgMarkup } from '../../utils/icon';
import NodeConfigModal from './NodeConfigModal.vue';

export interface FlowNodeData {
  type: string;
  actionId?: string;
  label: string;
  description?: string;
  config?: Record<string, unknown>;
  icon?: string;
  iconSvg?: string;
  contextLabel?: string;
  contextIconSvg?: string;
  onRequestDelete?: (id: string) => void;
  onEdit?: (id: string, data: { label: string; description: string; config?: Record<string, unknown> }) => void;
  onSelect?: (id: string) => void;
}

const props = defineProps<{
  id: string;
  data: FlowNodeData;
  selected?: boolean;
}>();

const fallbackConfig = computed(() => getFlowNodeConfig(props.data.type));
const isCondition = computed(() => props.data.type === 'condition');
const isTrigger = computed(() => props.data.type === 'trigger');
const menuRef = ref<HTMLElement | null>(null);
const menuOpen = ref(false);
const showEditModal = ref(false);

const resolvedIconSvg = computed(() => resolveSvgMarkup(props.data.iconSvg, props.data.icon));
const contextIconSvg = computed(() => String(props.data.contextIconSvg || '').trim());
const displayIcon = computed(() => String(props.data.icon || fallbackConfig.value?.icon || '').trim());
const displayColorClass = computed(() => fallbackConfig.value?.color || 'bg-slate-500');

onClickOutside(menuRef, () => {
  menuOpen.value = false;
});

function handleEdit(payload: { label: string; description: string; config?: Record<string, unknown> }) {
  props.data.onEdit?.(props.id, payload);
}

function requestDelete() {
  props.data.onRequestDelete?.(props.id);
  menuOpen.value = false;
}

function selectNode() {
  props.data.onSelect?.(props.id);
}

function iconGlyph(value: string) {
  const normalized = String(value || '').trim();
  return normalized ? normalized.slice(0, 1).toUpperCase() : 'A';
}

function isBoxiconClass(value: string) {
  return /^bx[lrs]?-/.test(String(value || '').trim());
}
</script>

<template>
  <div
    class="group relative min-w-[240px] max-w-[420px] rounded-xl border bg-white shadow-md transition-all duration-200 select-none"
    :class="[
      selected
        ? 'border-primary-600 ring-2 ring-primary-600/20'
        : 'border-slate-200 hover:border-slate-300 hover:shadow-lg',
    ]"
    @click="selectNode"
  >
    <Handle
      v-if="!isTrigger"
      id="input"
      type="target"
      :position="Position.Top"
      :style="{ top: '-7px', width: '12px', height: '12px', background: '#94a3b8', border: '2px solid white' }"
    />

    <div class="flex items-center gap-2.5 border-b border-slate-100 px-3 py-2.5">
      <div
        class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md text-white"
        :class="displayColorClass"
      >
        <span
          v-if="isTrigger && contextIconSvg"
          class="flow-node-context-icon flex h-full w-full items-center justify-center bg-white p-1"
          v-html="contextIconSvg"
        />
        <span
          v-else-if="resolvedIconSvg"
          class="flow-node-action-icon flex h-4 w-4 items-center justify-center"
          v-html="resolvedIconSvg"
        />
        <i
          v-else-if="isBoxiconClass(displayIcon)"
          :class="`bx ${displayIcon} text-white`"
          style="font-size: 14px;"
        />
        <span v-else class="text-[11px] font-semibold uppercase tracking-[0.18em]">
          {{ iconGlyph(displayIcon || data.label) }}
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <p
          v-if="isTrigger && data.contextLabel"
          class="truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400"
        >
          {{ data.contextLabel }}
        </p>
        <span class="block truncate text-sm font-semibold text-slate-900">
          {{ data.label }}
        </span>
      </div>

      <div v-if="!isTrigger" ref="menuRef" class="relative">
        <button
          type="button"
          class="rounded p-0.5 text-slate-400 opacity-0 transition-opacity hover:text-slate-700 group-hover:opacity-100"
          @click.stop="menuOpen = !menuOpen"
        >
          <DotsVerticalRounded :size="16" />
        </button>

        <div
          v-if="menuOpen"
          class="absolute right-0 top-full z-50 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
          @click.stop
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
            @click="showEditModal = true; menuOpen = false"
          >
            <i class="bx bx-edit" style="font-size: 13px;" />
            Editar
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 transition-colors hover:bg-red-50"
            @click="requestDelete"
          >
            <i class="bx bx-trash" style="font-size: 13px;" />
            Excluir
          </button>
        </div>
      </div>
    </div>

    <div v-if="data.description" class="px-3 py-2.5">
      <p class="line-clamp-3 whitespace-pre-line text-xs leading-relaxed text-slate-500">
        {{ data.description }}
      </p>
    </div>

    <template v-if="isCondition">
      <div class="flex items-end justify-between px-5 pb-3 pt-1">
        <div class="flex flex-col items-center gap-1">
          <span class="text-[10px] font-semibold text-emerald-600">Verdadeiro</span>
          <Handle
            id="true"
            type="source"
            :position="Position.Bottom"
            :style="{
              position: 'relative',
              transform: 'none',
              inset: 'auto',
              width: '12px',
              height: '12px',
              background: '#10b981',
              border: '2px solid #6ee7b7',
            }"
          />
        </div>
        <div class="flex flex-col items-center gap-1">
          <span class="text-[10px] font-semibold text-red-500">Falso</span>
          <Handle
            id="false"
            type="source"
            :position="Position.Bottom"
            :style="{
              position: 'relative',
              transform: 'none',
              inset: 'auto',
              width: '12px',
              height: '12px',
              background: '#ef4444',
              border: '2px solid #fca5a5',
            }"
          />
        </div>
      </div>
    </template>

    <template v-else>
      <Handle
        id="output"
        type="source"
        :position="Position.Bottom"
        :style="{ bottom: '-7px', width: '12px', height: '12px', background: '#94a3b8', border: '2px solid white' }"
      />
    </template>
  </div>

  <NodeConfigModal
    :open="showEditModal"
    :node-type="data.actionId || data.type"
    :label="data.label"
    :description="data.description ?? ''"
    :config="(data.config as Record<string, unknown>) ?? {}"
    @close="showEditModal = false"
    @save="handleEdit"
  />
</template>

<style scoped>
.flow-node-context-icon :deep(svg),
.flow-node-action-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
  color: currentColor;
  fill: currentColor;
}
</style>
