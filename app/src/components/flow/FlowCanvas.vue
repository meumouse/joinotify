<script setup lang="ts">
/**
 * FlowCanvas.vue
 *
 * Vue-flow-based node-graph canvas. Adapted from the React builder's FlowCanvas.tsx.
 * Uses @vue-flow/core as the Vue equivalent of ReactFlow.
 *
 * @since 1.4.7
 */
import { ref, computed } from 'vue';
import {
  VueFlow,
  useVueFlow,
  type Node,
  type Edge,
  type Connection,
  MarkerType,
  Position,
} from '@vue-flow/core';
import { Background, BackgroundVariant } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import FlowNode, { type FlowNodeData } from './FlowNode.vue';
import { getFlowNodeConfig, type FlowNodeConfig } from './flowNodeTypes';

// ─── Types ────────────────────────────────────────────────────────────────

export interface FlowCanvasExpose {
  clearCanvas(): void;
  getFlowData(): { nodes: Node[]; edges: Edge[] };
}

// ─── Props / Emits ────────────────────────────────────────────────────────

const props = defineProps<{
  triggerLabel?: string;
  triggerDescription?: string;
}>();

defineEmits<{
  (e: 'add-action'): void;
}>();

// ─── Unique ID helper ─────────────────────────────────────────────────────

let _idCounter = 1;
function uid() {
  return `node_${Date.now()}_${_idCounter++}`;
}

// ─── Nodes & Edges state ──────────────────────────────────────────────────

const nodes = ref<Node[]>([
  {
    id: 'trigger-1',
    type: 'flowNode',
    position: { x: 300, y: 80 },
    data: {
      type: 'trigger',
      label: props.triggerLabel ?? 'Acionamento',
      description: props.triggerDescription ?? 'Define o acionamento que inicia o fluxo.',
    } satisfies FlowNodeData,
  },
]);

const edges = ref<Edge[]>([]);

// ─── Vue Flow instance ────────────────────────────────────────────────────

const { onConnect, addEdges, project } = useVueFlow();

const defaultEdgeOptions: Partial<Edge> = {
  animated: true,
  style: { strokeWidth: 2, stroke: '#94a3b8' },
  markerEnd: MarkerType.ArrowClosed,
};

onConnect((params: Connection) => {
  addEdges([{ ...params, ...defaultEdgeOptions }]);
});

// ─── Node operations ──────────────────────────────────────────────────────

function deleteNode(id: string) {
  nodes.value = nodes.value.filter((n) => n.id !== id);
  edges.value = edges.value.filter((e) => e.source !== id && e.target !== id);
}

function duplicateNode(id: string) {
  const original = nodes.value.find((n) => n.id === id);
  if (!original) return;

  const newNode: Node = {
    ...original,
    id: uid(),
    position: { x: original.position.x + 40, y: original.position.y + 80 },
    data: { ...original.data },
  };

  nodes.value = [...nodes.value, newNode];
}

function editNode(
  id: string,
  payload: { label: string; description: string; config?: Record<string, unknown> },
) {
  nodes.value = nodes.value.map((n) =>
    n.id === id ? { ...n, data: { ...n.data, ...payload } } : n,
  );
}

// Inject callbacks into each node's data so FlowNode.vue can call them
const nodesWithCallbacks = computed<Node[]>(() =>
  nodes.value.map((n) => ({
    ...n,
    data: {
      ...n.data,
      onDelete: deleteNode,
      onDuplicate: duplicateNode,
      onEdit: editNode,
    },
  })),
);

// ─── Drag-and-drop ────────────────────────────────────────────────────────

const canvasRef = ref<HTMLDivElement | null>(null);

function onDragOver(event: DragEvent) {
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move';
  }
}

function onDrop(event: DragEvent) {
  event.preventDefault();

  const raw = event.dataTransfer?.getData('application/vueflow-node-type');
  if (!raw) return;

  let nodeConfig: FlowNodeConfig;
  try {
    nodeConfig = JSON.parse(raw) as FlowNodeConfig;
  } catch {
    return;
  }

  const bounds = canvasRef.value?.getBoundingClientRect();
  if (!bounds) return;

  const position = project({
    x: event.clientX - bounds.left,
    y: event.clientY - bounds.top,
  });

  const newNode: Node = {
    id: uid(),
    type: 'flowNode',
    position,
    data: {
      type: nodeConfig.type,
      label: nodeConfig.label,
      description: nodeConfig.description,
      config: {},
    } satisfies FlowNodeData,
  };

  nodes.value = [...nodes.value, newNode];
}

// ─── Clear ────────────────────────────────────────────────────────────────

function clearCanvas() {
  nodes.value = [];
  edges.value = [];
}

function getFlowData() {
  return { nodes: nodes.value, edges: edges.value };
}

defineExpose<FlowCanvasExpose>({ clearCanvas, getFlowData });

// ─── Custom node types registration ──────────────────────────────────────

const nodeTypes = { flowNode: FlowNode };
</script>

<template>
  <div ref="canvasRef" class="relative flex h-full w-full flex-col overflow-hidden">
    <VueFlow
      v-model:nodes="nodesWithCallbacks"
      v-model:edges="edges"
      :node-types="nodeTypes"
      :default-edge-options="defaultEdgeOptions"
      :fit-view-on-init="true"
      :delete-key-code="['Backspace', 'Delete']"
      class="bg-[#f8f9fb]"
      @dragover="onDragOver"
      @drop="onDrop"
    >
      <!-- Dot background -->
      <Background
        :variant="BackgroundVariant.Dots"
        :gap="22"
        :size="1.5"
        pattern-color="#d1d5db"
      />

      <!-- Navigation controls (bottom-left) -->
      <Controls position="bottom-left" />

      <!-- Minimap (bottom-right) -->
      <MiniMap
        position="bottom-right"
        node-color="#818cf8"
        mask-color="rgba(248,249,251,0.75)"
        :style="{ width: 150, height: 100 }"
      />
    </VueFlow>

    <!-- "Add action" floating button -->
    <button
      type="button"
      class="absolute right-4 top-4 z-10 flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-md transition hover:bg-primary-700"
      @click="$emit('add-action')"
    >
      <i class="bx bx-plus" style="font-size: 15px;" />
      Adicionar ação
    </button>
  </div>
</template>

<style>
/* ── Mandatory @vue-flow styles ── */
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
@import '@vue-flow/minimap/dist/style.css';
</style>
