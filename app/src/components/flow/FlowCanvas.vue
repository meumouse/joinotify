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
  type Connection,
  type Edge,
  type Node,
  type NodeChange,
} from '@vue-flow/core';
import { Background, BackgroundVariant } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MiniMap } from '@vue-flow/minimap';
import FlowEdge from './FlowEdge.vue';
import FlowNode, { type FlowNodeData } from './FlowNode.vue';
import {
  getActionDefinition,
  getActionRegistryPreview,
} from '../../registries/actionRegistry';
import { getActionRegistryRevision } from '../../builder/actions/registry/actionRegistry';
import { getTriggerContextDefinition, getTriggerDefinition } from '../../registries/triggerRegistry';
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

interface FlowEdgeRemovePayload {
  edgeId: string;
  sourceId: string;
  targetId: string;
  sourceHandle: string;
  targetHandle: string;
}

export interface FlowCanvasExpose {}

interface WorkflowConnectionMeta {
  source_id?: string;
  source_handle?: string;
  target_handle?: string;
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
const flowPositionCache = ref<Record<string, { x: number; y: number }>>({});
const syncedPositionCache = ref<Record<string, { x: number; y: number }>>({});
const actionRegistryRevision = computed(() => getActionRegistryRevision());

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

function isImageUrl(value: unknown): boolean {
  const source = String(value || '').trim();
  return /^https?:\/\//i.test(source) || /^data:image\//i.test(source) || source.startsWith('/');
}

function getConnectionMeta(node: WorkflowNode | null | undefined): WorkflowConnectionMeta | null {
  if (!node || !node.data || typeof node.data.connection_from !== 'object' || !node.data.connection_from) {
    return null;
  }

  return node.data.connection_from as WorkflowConnectionMeta;
}

function isFloatingNode(node: WorkflowNode | null | undefined): boolean {
  if (!node || !node.data) {
    return false;
  }

  return node.data.connection_mode === 'floating' || Boolean(node.data.connection_break_before);
}

function getVisibleNodeConfig(node: WorkflowNode): Record<string, unknown> {
  const config = { ...node.data };

  delete config.connection_from;
  delete config.connection_mode;
  delete config.connection_break_before;
  delete config.canvas_position;

  return config;
}

function buildNodeData(node: WorkflowNode): FlowNodeData {
  if (node.type === 'trigger') {
    const context = String(node.data?.context || '');
    const trigger = String(node.data?.trigger || '');
    const contextDefinition = getTriggerContextDefinition(context);
    const triggerDefinition = getTriggerDefinition(context, trigger);
    const contextIcon = String(contextDefinition?.icon || '').trim();
    const contextIconUrl = String(contextDefinition?.icon_url || '').trim() || (isImageUrl(contextIcon) ? contextIcon : '');

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
      config: getVisibleNodeConfig(node),
      icon: String(triggerDefinition?.icon || ''),
      iconSvg: String(triggerDefinition?.iconSvg || ''),
      contextLabel: String(contextDefinition?.label || context || 'Trigger'),
      contextIconSvg: String(contextDefinition?.icon_svg || ''),
      contextIcon: contextIcon,
      contextIconUrl: contextIconUrl,
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
    label: String(node.data?.title || actionDefinition?.label || actionId || 'Acao'),
    description,
    config: getVisibleNodeConfig(node),
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
      sourceId: source,
      targetId: target,
      sourceHandle,
      targetHandle,
      onRemove: handleRemoveEdge,
    },
  };
}

function toFiniteNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }

  return null;
}

function getStoredPosition(node: WorkflowNode) {
  const candidate = node.data?.canvas_position;
  const source = candidate && typeof candidate === 'object' ? (candidate as Record<string, unknown>) : null;
  const x = source ? toFiniteNumber(source.x) : null;
  const y = source ? toFiniteNumber(source.y) : null;

  if (x !== null && y !== null) {
    return {
      x,
      y,
    };
  }

  return null;
}

function flattenWorkflowNodes(nodes: WorkflowNode[] = []): WorkflowNode[] {
  const flattened: WorkflowNode[] = [];

  const walk = (items: WorkflowNode[]) => {
    items.forEach((item) => {
      flattened.push(item);

      if (Array.isArray(item.children) && item.children.length > 0) {
        walk(item.children);
      }

      if (isConditionNode(item)) {
        const branches = getBranchCollection(item);
        walk(branches.action_true || []);
        walk(branches.action_false || []);
      }
    });
  };

  walk(nodes);
  return flattened;
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
      const cachedPosition = flowPositionCache.value[workflowNode.id];
      const nodePosition = storedPosition || cachedPosition || { x: startX, y: currentY };

      flowNodes.push({
        id: workflowNode.id,
        type: 'flowNode',
        position: nodePosition,
        selected: workflowNode.id === props.selectedNodeId,
        draggable: true,
        connectable: true,
        data: buildNodeData(workflowNode),
      });
      flowPositionCache.value[workflowNode.id] = {
        x: Number(nodePosition.x),
        y: Number(nodePosition.y),
      };

      const connectionMeta = getConnectionMeta(workflowNode);

      if (connectionMeta?.source_id) {
        flowEdges.push(createEdge(
          connectionMeta.source_id,
          workflowNode.id,
          String(connectionMeta.source_handle || 'output'),
          String(connectionMeta.target_handle || 'input'),
        ));
      } else if (previousNodeId && !isFloatingNode(workflowNode)) {
        flowEdges.push(createEdge(previousNodeId, workflowNode.id));
      }

      let nextY = currentY + rowGapY;

      if (isConditionNode(workflowNode)) {
        const branches = getBranchCollection(workflowNode);
        const trueLayout = layoutSequence(branches.action_true, startX - branchOffsetX, currentY + rowGapY);
        const falseLayout = layoutSequence(branches.action_false, startX + branchOffsetX, currentY + rowGapY);

        if (trueLayout.firstId && !getConnectionMeta(branches.action_true[0]) && !isFloatingNode(branches.action_true[0])) {
          flowEdges.push(createEdge(workflowNode.id, trueLayout.firstId, 'true'));
        }

        if (falseLayout.firstId && !getConnectionMeta(branches.action_false[0]) && !isFloatingNode(branches.action_false[0])) {
          flowEdges.push(createEdge(workflowNode.id, falseLayout.firstId, 'false'));
        }

        nextY = Math.max(nextY, trueLayout.endY, falseLayout.endY) + branchExitGapY;
      }

      if (Array.isArray(workflowNode.children) && workflowNode.children.length > 0) {
        const childLayout = layoutSequence(workflowNode.children, startX, nextY);

        if (childLayout.firstId && !getConnectionMeta(workflowNode.children[0]) && !isFloatingNode(workflowNode.children[0])) {
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
  // Track action registry updates so nodes refresh when async metadata arrives.
  actionRegistryRevision.value;
  const nextGraph = buildFlowGraph(props.workflowNodes || []);
  const nextNodeIds = new Set(nextGraph.nodes.map((node) => String(node.id)));

  for (const nodeId of Object.keys(flowPositionCache.value)) {
    if (!nextNodeIds.has(nodeId)) {
      delete flowPositionCache.value[nodeId];
    }
  }

  return {
    nodes: nextGraph.nodes,
    edges: nextGraph.edges,
  };
});

watch(
  graph,
  (nextGraph) => {
    const nodesById = new Map(nextGraph.nodes.map((node) => [String(node.id), node]));
    const workflowNodes = flattenWorkflowNodes(props.workflowNodes || []);

    workflowNodes.forEach((workflowNode) => {
      if (getStoredPosition(workflowNode)) {
        return;
      }

      const flowNode = nodesById.get(String(workflowNode.id));

      if (!flowNode) {
        return;
      }

      const x = Number(flowNode.position?.x);
      const y = Number(flowNode.position?.y);

      if (!Number.isFinite(x) || !Number.isFinite(y)) {
        return;
      }

      emit('update-node', {
        nodeId: String(workflowNode.id),
        patch: {
          canvas_position: { x, y },
        },
      });
    });
  },
  { immediate: true },
);

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

function handleRemoveEdge(payload: FlowEdgeRemovePayload | string) {
  const targetNodeId = typeof payload === 'string'
    ? String(payload.split('->')[1] || '').split(':')[0]
    : String(payload?.targetId || '');

  if (!targetNodeId) {
    return;
  }

  emit('update-node', {
    nodeId: targetNodeId,
    patch: {
      connection_from: null,
      connection_mode: 'floating',
      connection_break_before: null,
      ...(flowPositionCache.value[targetNodeId]
        ? {
            canvas_position: {
              x: Number(flowPositionCache.value[targetNodeId].x),
              y: Number(flowPositionCache.value[targetNodeId].y),
            },
          }
        : {}),
    },
  });
}

function handleConnect(connection: Connection) {
  const targetNodeId = String(connection.target || '').trim();

  if (!targetNodeId) {
    return;
  }

  emit('update-node', {
    nodeId: targetNodeId,
    patch: {
      connection_from: {
        source_id: String(connection.source || '').trim(),
        source_handle: String(connection.sourceHandle || 'output'),
        target_handle: String(connection.targetHandle || 'input'),
      },
      connection_mode: null,
      connection_break_before: null,
    },
  });
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

function syncNodePosition(nodeId: string, x: number, y: number) {
  if (!nodeId || !Number.isFinite(x) || !Number.isFinite(y)) {
    return;
  }

  const normalized = {
    x: Number(x),
    y: Number(y),
  };
  const previous = syncedPositionCache.value[nodeId];

  flowPositionCache.value[nodeId] = normalized;

  if (previous && previous.x === normalized.x && previous.y === normalized.y) {
    return;
  }

  syncedPositionCache.value[nodeId] = normalized;

  emit('update-node', {
    nodeId,
    patch: {
      canvas_position: normalized,
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

  syncNodePosition(String(node.id), Number(node.position.x), Number(node.position.y));
}

function onNodesChange(changes: NodeChange[]) {
  (changes || []).forEach((change) => {
    const source = change as NodeChange & { id?: string; type?: string; position?: { x?: number; y?: number } };

    if (source.type !== 'position' || !source.id || !source.position) {
      return;
    }

    syncNodePosition(
      String(source.id),
      Number(source.position.x),
      Number(source.position.y),
    );
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
      :nodes-connectable="true"
      :connect-on-click="true"
      :elements-selectable="true"
      class="bg-[#f8f9fb]"
      @dragover="onDragOver"
      @drop="onDrop"
      @connect="handleConnect"
      @node-drag-stop="onNodeDragStop"
      @nodes-change="onNodesChange"
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
      Adicionar acao
    </button>
  </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
@import '@vue-flow/minimap/dist/style.css';
</style>
