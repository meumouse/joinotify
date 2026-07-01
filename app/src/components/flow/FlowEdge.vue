<script setup lang="ts">
/**
 * FlowEdge.vue
 *
 * Custom Vue Flow edge that draws a bezier connection between two nodes and
 * reveals a delete button at the edge midpoint on hover. Clicking the button
 * invokes the onRemove callback supplied via edge data to remove the connection.
 *
 * @since 2.0.0
 */
import { computed, onBeforeUnmount, ref } from 'vue';
import TrashAlt from '@boxicons/vue/TrashAlt';
import { __, textDomain } from '../../utils/i18n';
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

/**
 * Cancel any pending hover hide timer.
 *
 * @since 2.0.0
 * @returns {void}
 */
function clearHoverTimer() {
  if (hideHoverTimer) {
    window.clearTimeout(hideHoverTimer);
    hideHoverTimer = null;
  }
}

/**
 * Mark the edge as hovered when the pointer enters it.
 *
 * @since 2.0.0
 * @returns {void}
 */
function handleMouseEnter() {
  clearHoverTimer();
  hovered.value = true;
}

/**
 * Clear the hover state after a short delay when the pointer leaves the edge,
 * allowing the pointer to reach the delete button without flicker.
 *
 * @since 2.0.0
 * @returns {void}
 */
function handleMouseLeave() {
  clearHoverTimer();
  hideHoverTimer = window.setTimeout(() => {
    hovered.value = false;
    hideHoverTimer = null;
  }, 80);
}

/**
 * Track that the pointer is over the delete button so it stays visible.
 *
 * @since 2.0.0
 * @returns {void}
 */
function handleButtonEnter() {
  clearHoverTimer();
  hoveringButton.value = true;
}

/**
 * Hide the delete button and clear the edge hover state when the pointer leaves the button.
 *
 * @since 2.0.0
 * @returns {void}
 */
function handleButtonLeave() {
  hoveringButton.value = false;
  hovered.value = false;
}

/**
 * Invoke the onRemove callback with the edge and its endpoints so the parent
 * can delete this connection.
 *
 * @since 2.0.0
 * @returns {void}
 */
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
      :aria-label="__('Delete connection', textDomain)"
      @mouseenter="handleButtonEnter"
      @mouseleave="handleButtonLeave"
      @click.stop="removeTargetNode"
    >
      <TrashAlt class="h-4 w-4" />
    </button>
  </EdgeLabelRenderer>
</template>
