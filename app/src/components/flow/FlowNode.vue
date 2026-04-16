<script setup lang="ts">
/**
 * FlowNode.vue
 *
 * Custom node component for the vue-flow canvas.
 * Adapted from the React builder's FlowNode.tsx.
 *
 * @since 1.4.7
 */
import { computed, ref } from 'vue';
import { Handle, Position } from '@vue-flow/core';
import { onClickOutside } from '@vueuse/core';
import { getFlowNodeConfig } from './flowNodeTypes';
import NodeConfigModal from './NodeConfigModal.vue';

export interface FlowNodeData {
  type: string;
  label: string;
  description?: string;
  config?: Record<string, unknown>;
  onDelete?: (id: string) => void;
  onDuplicate?: (id: string) => void;
  onEdit?: (id: string, data: { label: string; description: string; config?: Record<string, unknown> }) => void;
}

const props = defineProps<{
  id: string;
  data: FlowNodeData;
  selected?: boolean;
}>();

const nodeConfig = computed(() => getFlowNodeConfig(props.data.type));
const isCondition = computed(() => props.data.type === 'condition');
const isTrigger = computed(() => props.data.type === 'trigger');

const menuRef = ref<HTMLElement | null>(null);
const menuOpen = ref(false);
const showDeleteConfirm = ref(false);
const showEditModal = ref(false);

onClickOutside(menuRef, () => {
  menuOpen.value = false;
});

function handleDelete() {
  props.data.onDelete?.(props.id);
  showDeleteConfirm.value = false;
}

function handleDuplicate() {
  props.data.onDuplicate?.(props.id);
  menuOpen.value = false;
}

function handleEdit(payload: { label: string; description: string; config?: Record<string, unknown> }) {
  props.data.onEdit?.(props.id, payload);
}
</script>

<template>
  <!-- ─── Node card ─── -->
  <div
    class="group relative min-w-[220px] max-w-[400px] rounded-xl border bg-white shadow-md transition-all duration-200 select-none"
    :class="[
      selected
        ? 'border-primary-600 ring-2 ring-primary-600/20'
        : 'border-slate-200 hover:border-slate-300 hover:shadow-lg',
    ]"
  >
    <!-- Target handle (top) — not rendered for trigger -->
    <Handle
      v-if="!isTrigger"
      id="input"
      type="target"
      :position="Position.Top"
      :style="{ top: '-7px', width: '12px', height: '12px', background: '#94a3b8', border: '2px solid white' }"
    />

    <!-- ── Header ── -->
    <div class="flex items-center gap-2.5 px-3 py-2.5 border-b border-slate-100">
      <div
        class="flex items-center justify-center w-7 h-7 rounded-md shrink-0"
        :class="nodeConfig?.color ?? 'bg-slate-400'"
      >
        <i :class="`bx ${nodeConfig?.icon ?? 'bx-cog'} text-white`" style="font-size: 14px;" />
      </div>

      <span class="font-semibold text-sm text-slate-900 flex-1 truncate">
        {{ data.label }}
      </span>

      <!-- Actions dropdown (hidden for trigger) -->
      <div v-if="!isTrigger" ref="menuRef" class="relative">
        <button
          type="button"
          class="opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-slate-700 p-0.5 rounded"
          @click.stop="menuOpen = !menuOpen"
        >
          <i class="bx bx-dots-vertical-rounded" style="font-size: 16px;" />
        </button>

        <div
          v-if="menuOpen"
          class="absolute right-0 top-full z-50 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
          @click.stop
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
            @click="showEditModal = true; menuOpen = false"
          >
            <i class="bx bx-edit" style="font-size: 13px;" />
            Editar
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
            @click="handleDuplicate"
          >
            <i class="bx bx-copy" style="font-size: 13px;" />
            Duplicar
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
            @click="showDeleteConfirm = true; menuOpen = false"
          >
            <i class="bx bx-trash" style="font-size: 13px;" />
            Remover
          </button>
        </div>
      </div>
    </div>

    <!-- ── Body ── -->
    <div v-if="data.description" class="px-3 py-2.5">
      <p class="text-xs text-slate-500 leading-relaxed whitespace-pre-line line-clamp-3">
        {{ data.description }}
      </p>
    </div>

    <!-- ── Condition: two labelled source handles ── -->
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

    <!-- ── Single source handle (bottom) ── -->
    <template v-else>
      <Handle
        id="output"
        type="source"
        :position="Position.Bottom"
        :style="{ bottom: '-7px', width: '12px', height: '12px', background: '#94a3b8', border: '2px solid white' }"
      />
    </template>
  </div>

  <!-- ─── Delete confirmation dialog ─── -->
  <Teleport to="body">
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-[9999] flex items-center justify-center px-4"
    >
      <button
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        type="button"
        @click="showDeleteConfirm = false"
      />
      <div class="relative z-10 w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-semibold text-slate-900">Remover ação</h3>
        <p class="mt-2 text-sm text-slate-500">
          Tem certeza que deseja remover <strong>"{{ data.label }}"</strong> do fluxo?
          Esta ação não pode ser desfeita.
        </p>
        <div class="mt-5 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            @click="showDeleteConfirm = false"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition-colors"
            @click="handleDelete"
          >
            Remover
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ─── Edit / Config modal ─── -->
  <NodeConfigModal
    :open="showEditModal"
    :node-type="data.type"
    :label="data.label"
    :description="data.description ?? ''"
    :config="(data.config as Record<string, unknown>) ?? {}"
    @close="showEditModal = false"
    @save="handleEdit"
  />
</template>
