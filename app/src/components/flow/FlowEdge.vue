<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import {
  BaseEdge,
  EdgeLabelRenderer,
  getBezierPath,
  type EdgeProps,
} from '@vue-flow/core';

interface FlowEdgeRemovePayload {
  edgeId: string;
  sourceId: string;
  targetId: string;
  sourceHandle: string;
  targetHandle: string;
}

interface FlowEdgeData {
  edgeId?: string;
  onRemove?: (payload: FlowEdgeRemovePayload) => void;
}

const props = defineProps<EdgeProps<FlowEdgeData>>();

const hovered = ref(false);
const hoveringButton = ref(false);
let hideHoverTimer: ReturnType<typeof window.setTimeout> | null = null;

const bezier = computed(() => getBezierPath({
  sourceX: props.sourceX,
  sourceY: props.sourceY,
  sourcePosition: props.sourcePosition,
  targetX: props.targetX,
  targetY: props.targetY,
  targetPosition: props.targetPosition,
  curvature: props.curvature,
}));

const edgePath = computed(() => bezier.value[0]);
const labelX = computed(() => bezier.value[1]);
const labelY = computed(() => bezier.value[2]);
const showRemoveButton = computed(() => hovered.value || hoveringButton.value);

function clearHoverTimer() {
  if (hideHoverTimer) {
    window.clearTimeout(hideHoverTimer);
    hideHoverTimer = null;
  }
}

function handleMouseEnter() {
  clearHoverTimer();
  hovered.value = true;
}

function handleMouseLeave() {
  clearHoverTimer();
  hideHoverTimer = window.setTimeout(() => {
    hovered.value = false;
    hideHoverTimer = null;
  }, 80);
}

function handleButtonEnter() {
  clearHoverTimer();
  hoveringButton.value = true;
}

function handleButtonLeave() {
  hoveringButton.value = false;
  hovered.value = false;
}

function removeTargetNode() {
  const edgeId = String(props.data?.edgeId || props.id || '').trim();

  if (!edgeId) {
    return;
  }

  props.data?.onRemove?.({
    edgeId,
    sourceId: String(props.source || ''),
    targetId: String(props.target || ''),
    sourceHandle: String(props.sourceHandle || ''),
    targetHandle: String(props.targetHandle || ''),
  });
}

onBeforeUnmount(() => {
  clearHoverTimer();
});
</script>

<template>
  <g
    class="flow-edge"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <BaseEdge
      :id="id"
      :path="edgePath"
      :marker-end="markerEnd"
      :marker-start="markerStart"
      :style="style"
      :interaction-width="interactionWidth || 28"
    />
  </g>

  <EdgeLabelRenderer>
    <button
      v-if="showRemoveButton && data?.edgeId"
      type="button"
      class="nodrag nopan absolute flex h-7 w-7 items-center justify-center rounded-full border border-rose-200 bg-white text-rose-600 shadow-md transition hover:bg-rose-50"
      :style="{
        transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
        pointerEvents: 'all',
      }"
      aria-label="Excluir conexao"
      @mouseenter="handleButtonEnter"
      @mouseleave="handleButtonLeave"
      @click.stop="removeTargetNode"
    >
      <i class="bx bx-trash" style="font-size: 14px;" />
    </button>
  </EdgeLabelRenderer>
</template>
