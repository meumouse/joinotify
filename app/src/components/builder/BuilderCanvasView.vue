<script setup lang="ts">
/**
 * BuilderCanvasView.vue
 *
 * Main canvas container. Uses FlowCanvas (@vue-flow/core) as the primary
 * editing surface and shows the action-library sidebar on the right.
 *
 * @since 1.4.7
 */
import { computed, ref } from 'vue';
import { GitRepoForked } from '@boxicons/vue';
import { __, textDomain } from '../../utils/i18n';
import FlowCanvas, { type FlowCanvasExpose } from '../flow/FlowCanvas.vue';
import BuilderLoader from './BuilderLoader.vue';
import ActionLibraryModal from '../../builder/actions/components/ActionLibraryModal.vue';
import NodeSettingsDrawer from '../settings/NodeSettingsDrawer.vue';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import type { WorkflowContextDefinition, WorkflowNode } from '../../types/workflowBuilder';

type BuilderAction = Record<string, unknown>;

interface BuilderCanvasProps {
  triggerNode: WorkflowNode | null;
  nodes: WorkflowNode[];
  selectedNodeId: string;
  selectedNode: WorkflowNode | null;
  contexts: WorkflowContextDefinition[];
  drawerOpen: boolean;
  loading: boolean;
  actions: BuilderAction[];
  actionCategories: BuilderAction[];
  actionsLoading: boolean;
  actionsOpen: boolean;
  flowReady: boolean;
  readyTrigger: boolean;
  readyActions: boolean;
  readySenders: boolean;
}

const props = defineProps<BuilderCanvasProps>();

const emit = defineEmits([
  'select-node',
  'change-trigger',
  'add-node',
  'duplicate-node',
  'remove-node',
  'move-node',
  'open-actions',
  'update-node',
  'close-drawer',
  'test',
  'export',
  'select-action',
  'close-actions',
]);

const triggerDefinition = computed(() => {
  if (!props.triggerNode) return undefined;
  return getTriggerDefinition(
    String(props.triggerNode.data?.context ?? ''),
    String(props.triggerNode.data?.trigger ?? ''),
  );
});

const triggerLabel = computed(() =>
  String(
    triggerDefinition.value?.label ??
    props.triggerNode?.data?.title ??
    __('Trigger', textDomain),
  ),
);

const triggerDescription = computed(() =>
  String(
    triggerDefinition.value?.description ??
    props.triggerNode?.data?.description ??
    __('Define the trigger that starts the workflow', textDomain),
  ),
);

const flowCanvasRef = ref<FlowCanvasExpose | null>(null);
const showFlowLoader = computed(() => props.loading || !props.flowReady);

/**
 * Loader stages, in order. Each stage maps to a real data dependency and is
 * marked `done` once that data has arrived, so the message below the loader
 * reflects whatever request is currently in flight (never a fixed timer).
 *
 * The first stage ("Initializing builder") is considered done as soon as
 * any data has been received, so it only shows on the very first paint.
 */
const loaderStages = computed(() => {
  const received = props.readySenders || props.readyTrigger || props.readyActions;

  return [
    { done: received, label: __('Initializing builder', textDomain) },
    { done: props.readySenders, label: __('Loading senders...', textDomain) },
    { done: props.readyTrigger, label: __('Loading trigger...', textDomain) },
    { done: props.readyActions, label: __('Loading actions...', textDomain) },
  ];
});

const loaderMessage = computed(() => {
  const active = loaderStages.value.find((stage) => !stage.done);

  return active ? active.label : loaderStages.value[loaderStages.value.length - 1].label;
});

function openActionsSidebar(payload?: { afterNodeId?: string; branchKey?: string }) {
  emit('open-actions', {
    afterNodeId: payload?.afterNodeId || props.triggerNode?.id || '',
    branchKey: payload?.branchKey || '',
  });
}
</script>

<template>
  <div class="relative h-full min-h-0 w-full overflow-hidden">
    <div
      v-if="!triggerNode"
      class="flex h-full items-center justify-center bg-[#f8f9fb]"
    >
      <div class="max-w-md px-6 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
          <GitRepoForked class="text-slate-400" :width="28" :height="28" />
        </div>
        <h3 class="text-xl font-semibold tracking-tight text-slate-900">
          {{ __('Empty canvas', textDomain) }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ __('Create or import a workflow, choose a trigger, and start adding actions.', textDomain) }}
        </p>
      </div>
    </div>

    <FlowCanvas
      v-else
      ref="flowCanvasRef"
      class="h-full w-full"
      :trigger-label="triggerLabel"
      :trigger-description="triggerDescription"
      :workflow-nodes="nodes"
      :selected-node-id="selectedNodeId"
      @add-action="openActionsSidebar"
      @remove-node="$emit('remove-node', $event)"
      @select-node="$emit('select-node', $event)"
      @change-trigger="$emit('change-trigger', $event)"
      @select-action="$emit('select-action', $event)"
      @update-node="$emit('update-node', $event)"
    />

    <Transition name="flow-loader-fade">
      <div
        v-if="showFlowLoader"
        class="canvas-flow-loader fixed inset-0 z-[9999999] flex flex-col items-center justify-center bg-white px-4"
        role="status"
        aria-live="polite"
        :aria-label="__('Loading flow canvas', textDomain)"
      >
        <BuilderLoader />

        <div class="canvas-flow-loader__text mt-6 flex h-6 items-center justify-center overflow-hidden">
          <Transition name="loader-text-swap" mode="out-in">
            <span
              :key="loaderMessage"
              class="block text-sm font-medium text-slate-600"
            >
              {{ loaderMessage }}
            </span>
          </Transition>
        </div>
      </div>
    </Transition>

    <NodeSettingsDrawer
      :open="drawerOpen"
      :node="selectedNode"
      :contexts="contexts"
      @close="$emit('close-drawer')"
      @update="$emit('update-node', $event)"
    />

    <ActionLibraryModal
      :open="actionsOpen"
      :actions="actions"
      :categories="actionCategories"
      :loading="actionsLoading"
      :context="String(triggerNode?.data?.context ?? '')"
      @close="$emit('close-actions')"
      @select="$emit('select-action', $event)"
    />
  </div>
</template>

<style scoped>
.loader-text-swap-enter-active,
.loader-text-swap-leave-active {
  transition: opacity 0.32s ease, transform 0.32s ease;
}

.loader-text-swap-enter-from {
  opacity: 0;
  transform: translateY(8px);
}

.loader-text-swap-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.flow-loader-fade-enter-active,
.flow-loader-fade-leave-active {
  transition: opacity 0.42s ease, transform 0.42s ease;
}

.flow-loader-fade-enter-from,
.flow-loader-fade-leave-to {
  opacity: 0;
  transform: scale(0.98);
}
</style>
