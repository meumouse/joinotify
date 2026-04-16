<script setup lang="ts">
import { computed, ref } from 'vue';
import {
  BaseEdge,
  EdgeLabelRenderer,
  getBezierPath,
  type EdgeProps,
} from '@vue-flow/core';

interface FlowEdgeData {
  edgeId?: string;
  onRemove?: (edgeId: string) => void;
}

const props = defineProps<EdgeProps<FlowEdgeData>>();

const hovered = ref(false);

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

function removeTargetNode() {
  const edgeId = String(props.data?.edgeId || props.id || '').trim();

  if (!edgeId) {
    return;
  }

  props.data?.onRemove?.(edgeId);
}
</script>

<template>
  <g
    class="flow-edge"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
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
      v-if="hovered && data?.edgeId"
      type="button"
      class="nodrag nopan absolute flex h-7 w-7 items-center justify-center rounded-full border border-rose-200 bg-white text-rose-600 shadow-md transition hover:bg-rose-50"
      :style="{
        transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
        pointerEvents: 'all',
      }"
      aria-label="Excluir conexão"
      @click.stop="removeTargetNode"
    >
      <i class="bx bx-trash" style="font-size: 14px;" />
    </button>
  </EdgeLabelRenderer>
</template>
