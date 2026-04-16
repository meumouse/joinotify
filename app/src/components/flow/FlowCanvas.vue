<script setup lang="ts">
/**
 * FlowCanvas.vue
 *
 * Vue-flow-based canvas backed by the workflow tree from the builder store.
 * The graph is derived from workflow nodes so the flow stays aligned with the
 * persisted workflow structure.
 *
 * @since 1.4.7
 */
import { computed, ref, watch } from 'vue';
import {
  VueFlow,
  MarkerType,
  type Edge,
  type Node,
} from '@vue-flow/core';
import { Background, BackgroundVariant } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import FlowEdge from './FlowEdge.vue';
import FlowNode, { type FlowNodeData } from './FlowNode.vue';
import { getActionDefinition, getActionRegistryPreview } from '../../registries/actionRegistry';
import { getTriggerContextById } from '../../registries/triggerContexts';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import {
  getBranchCollection,
  isConditionNode,
} from '../../utils/workflowTree';
import type { WorkflowNode } from '../../types/workflowBuilder';

interface NodeEditEvent {
  nodeId: string;
  patch: Record<string, unknown>;
}

interface DroppedActionPayload {
  action?: string;
  id?: string;
  slug?: string;
  [key: string]: unknown;
}

const props = defineProps<{
  triggerLabel?: string;
  triggerDescription?: string;
  workflowNodes?: WorkflowNode[];
  selectedNodeId?: string;
}>();

const emit = defineEmits<{
  (e: 'add-action', payload?: { afterNodeId?: string; branchKey?: string }): void;
  (e: 'remove-node', nodeId: string): void;
  (e: 'select-node', nodeId: string): void;
  (e: 'select-action', payload: DroppedActionPayload): void;
  (e: 'update-node', payload: NodeEditEvent): void;
}>();

const canvasRef = ref<HTMLDivElement | null>(null);
const hiddenEdgeIds = ref<string[]>([]);

watch(
  () => props.workflowNodes,
  () => {
    hiddenEdgeIds.value = [];
  },
  { deep: true },
);

const defaultEdgeOptions: Partial<Edge> = {
  type: 'flowEdge',
  animated: true,
  interactionWidth: 28,
  style: { strokeWidth: 2, stroke: '#94a3b8' },
  markerEnd: MarkerType.ArrowClosed,
};

const nodeTypes = { flowNode: FlowNode };
const edgeTypes = { flowEdge: FlowEdge };

function resolveActionIcon(actionId: string) {
  const definition = getActionDefinition(actionId);

  return {
    icon: String(definition?.icon || '').trim(),
    iconSvg: String(definition?.iconSvg || '').trim(),
  };
}

function buildNodeData(node: WorkflowNode): FlowNodeData {
  if (node.type === 'trigger') {
    const context = String(node.data?.context || '');
    const trigger = String(node.data?.trigger || '');
    const contextDefinition = getTriggerContextById(context);
    const triggerDefinition = getTriggerDefinition(context, trigger);

    return {
      type: 'trigger',
      actionId: trigger,
      label: String(
        node.data?.title
        || triggerDefinition?.label
        || props.triggerLabel
        || 'Acionamento',
      ),
      description: String(
        node.data?.description
        || triggerDefinition?.description
        || props.triggerDescription
        || 'Define o acionamento que inicia o fluxo.',
      ),
      config: { ...node.data },
      icon: String(triggerDefinition?.icon || ''),
      iconSvg: String(triggerDefinition?.iconSvg || ''),
      contextLabel: String(contextDefinition?.label || context || 'Trigger'),
      contextIconSvg: String(contextDefinition?.icon_svg || ''),
      onEdit: handleNodeEdit,
      onRequestDelete: handleRemoveRequest,
      onSelect: handleNodeSelect,
    };
  }

  const actionId = String(node.data?.action || '');
  const actionDefinition = getActionDefinition(actionId);
  const { icon, iconSvg } = resolveActionIcon(actionId);
  const description = String(node.data?.description || getActionRegistryPreview(node) || actionDefinition?.description || '');

  return {
    type: isConditionNode(node) ? 'condition' : 'action',
    actionId,
    label: String(node.data?.title || actionDefinition?.label || actionId || 'Ação'),
    description,
    config: { ...node.data },
    icon,
    iconSvg,
    onEdit: handleNodeEdit,
    onRequestDelete: handleRemoveRequest,
    onSelect: handleNodeSelect,
  };
}

function createEdge(
  source: string,
  target: string,
  sourceHandle = 'output',
  targetHandle = 'input',
): Edge {
  return {
    id: `${source}:${sourceHandle}->${target}:${targetHandle}`,
    source,
    target,
    sourceHandle,
    targetHandle,
    ...defaultEdgeOptions,
    data: {
      edgeId: `${source}:${sourceHandle}->${target}:${targetHandle}`,
      onRemove: handleRemoveEdge,
    },
  };
}

function getStoredPosition(node: WorkflowNode) {
  const candidate = node.data?.canvas_position;

  if (
    candidate
    && typeof candidate === 'object'
    && typeof (candidate as Record<string, unknown>).x === 'number'
    && typeof (candidate as Record<string, unknown>).y === 'number'
  ) {
    return {
      x: Number((candidate as Record<string, unknown>).x),
      y: Number((candidate as Record<string, unknown>).y),
    };
  }

  return null;
}

function buildFlowGraph(workflowNodes: WorkflowNode[] = []) {
  const flowNodes: Node[] = [];
  const flowEdges: Edge[] = [];
  const branchOffsetX = 260;
  const rowGapY = 180;
  const branchExitGapY = 120;

  const layoutSequence = (
    sequence: WorkflowNode[],
    startX: number,
    startY: number,
  ): { firstId?: string; endY: number } => {
    let cursorY = startY;
    let previousNodeId = '';
    let firstId = '';

    sequence.forEach((workflowNode) => {
      if (!firstId) {
        firstId = workflowNode.id;
      }

      const currentY = cursorY;

      const storedPosition = getStoredPosition(workflowNode);

      flowNodes.push({
        id: workflowNode.id,
        type: 'flowNode',
        position: storedPosition || { x: startX, y: currentY },
        selected: workflowNode.id === props.selectedNodeId,
        draggable: true,
        data: buildNodeData(workflowNode),
      });

      if (previousNodeId) {
        flowEdges.push(createEdge(previousNodeId, workflowNode.id));
      }

      let nextY = currentY + rowGapY;

      if (isConditionNode(workflowNode)) {
        const branches = getBranchCollection(workflowNode);
        const trueLayout = layoutSequence(branches.action_true, startX - branchOffsetX, currentY + rowGapY);
        const falseLayout = layoutSequence(branches.action_false, startX + branchOffsetX, currentY + rowGapY);

        if (trueLayout.firstId) {
          flowEdges.push(createEdge(workflowNode.id, trueLayout.firstId, 'true'));
        }

        if (falseLayout.firstId) {
          flowEdges.push(createEdge(workflowNode.id, falseLayout.firstId, 'false'));
        }

        nextY = Math.max(nextY, trueLayout.endY, falseLayout.endY) + branchExitGapY;
      }

      if (Array.isArray(workflowNode.children) && workflowNode.children.length > 0) {
        const childLayout = layoutSequence(workflowNode.children, startX, nextY);

        if (childLayout.firstId) {
          flowEdges.push(createEdge(workflowNode.id, childLayout.firstId));
        }

        nextY = childLayout.endY + branchExitGapY;
      }

      previousNodeId = workflowNode.id;
      cursorY = nextY;
    });

    return {
      firstId,
      endY: sequence.length ? cursorY : startY,
    };
  };

  layoutSequence(workflowNodes, 320, 80);

  return { nodes: flowNodes, edges: flowEdges };
}

const graph = computed(() => {
  const nextGraph = buildFlowGraph(props.workflowNodes || []);

  return {
    nodes: nextGraph.nodes,
    edges: nextGraph.edges.filter((edge) => !hiddenEdgeIds.value.includes(String(edge.id))),
  };
});

function handleNodeSelect(nodeId: string) {
  if (!nodeId) {
    return;
  }

  emit('select-node', nodeId);
}

function handleRemoveRequest(nodeId: string) {
  if (!nodeId) {
    return;
  }

  emit('remove-node', nodeId);
}

function handleRemoveEdge(edgeId: string) {
  if (!edgeId || hiddenEdgeIds.value.includes(edgeId)) {
    return;
  }

  hiddenEdgeIds.value = [...hiddenEdgeIds.value, edgeId];
}

function handleNodeEdit(
  nodeId: string,
  payload: { label: string; description: string; config?: Record<string, unknown> },
) {
  const config = { ...(payload.config || {}) };

  delete config.title;
  delete config.description;

  emit('update-node', {
    nodeId,
    patch: {
      title: payload.label,
      description: payload.description,
      ...config,
    },
  });
}

function onDragOver(event: DragEvent) {
  event.preventDefault();

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move';
  }
}

function onDrop(event: DragEvent) {
  event.preventDefault();

  const raw = event.dataTransfer?.getData('application/vueflow-node-type');

  if (!raw) {
    return;
  }

  try {
    emit('select-action', JSON.parse(raw) as DroppedActionPayload);
  } catch {
    // Ignore malformed payloads dropped from outside the action library.
  }
}

function onNodeDragStop(_event: unknown, node: Node) {
  if (!node?.id) {
    return;
  }

  emit('update-node', {
    nodeId: String(node.id),
    patch: {
      canvas_position: {
        x: Number(node.position.x),
        y: Number(node.position.y),
      },
    },
  });
}

function openAddAction() {
  emit('add-action', {
    afterNodeId: props.selectedNodeId || props.workflowNodes?.[0]?.id || '',
  });
}
</script>

<template>
  <div ref="canvasRef" class="relative flex h-full w-full flex-col overflow-hidden">
    <VueFlow
      :nodes="graph.nodes"
      :edges="graph.edges"
      :node-types="nodeTypes"
      :edge-types="edgeTypes"
      :default-edge-options="defaultEdgeOptions"
      :fit-view-on-init="true"
      :delete-key-code="[]"
      :nodes-draggable="true"
      :nodes-connectable="false"
      :elements-selectable="true"
      class="bg-[#f8f9fb]"
      @dragover="onDragOver"
      @drop="onDrop"
      @node-drag-stop="onNodeDragStop"
    >
      <Background
        :variant="BackgroundVariant.Dots"
        :gap="22"
        :size="1.5"
        pattern-color="#d1d5db"
      />

      <Controls position="bottom-right" />

      <MiniMap
        position="bottom-left"
        node-color="#818cf8"
        mask-color="rgba(248,249,251,0.75)"
        :style="{ width: 180, height: 112 }"
      />
    </VueFlow>

    <button
      type="button"
      class="absolute right-4 top-4 z-10 flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-md transition hover:bg-primary-700"
      @click="openAddAction"
    >
      <i class="bx bx-plus" style="font-size: 15px;" />
      Adicionar ação
    </button>
  </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
@import '@vue-flow/minimap/dist/style.css';
</style>
