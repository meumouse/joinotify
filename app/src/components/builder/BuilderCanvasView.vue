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
import { __, textDomain } from '../../utils/i18n';
import FlowCanvas, { type FlowCanvasExpose } from '../flow/FlowCanvas.vue';
import BuilderActionSidebar from './BuilderActionSidebar.vue';
import NodeSettingsDrawer from '../settings/NodeSettingsDrawer.vue';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import type { WorkflowContextDefinition, WorkflowNode } from '../../types/workflowBuilder';

type BuilderAction = Record<string, unknown>;

// ── Props ─────────────────────────────────────────────────────────────────

interface BuilderCanvasProps {
  triggerNode: WorkflowNode | null;
  nodes: WorkflowNode[];
  selectedNodeId: string;
  selectedNode: WorkflowNode | null;
  contexts: WorkflowContextDefinition[];
  drawerOpen: boolean;
  loading: boolean;
  actions: BuilderAction[];
  actionsLoading: boolean;
  actionsOpen: boolean;
}

const props = defineProps<BuilderCanvasProps>();

// ── Emits ─────────────────────────────────────────────────────────────────

const emit = defineEmits([
  'select-node',
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

// ── Trigger info (used as the initial trigger node label/description) ────

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
    __('Define o acionamento para iniciar o fluxo.', textDomain),
  ),
);

// ── FlowCanvas imperative ref ─────────────────────────────────────────────

const flowCanvasRef = ref<FlowCanvasExpose | null>(null);

function openActionsSidebar() {
  emit('open-actions', { afterNodeId: props.triggerNode?.id ?? '' });
}
</script>

<template>
  <div class="relative h-full min-h-0 w-full overflow-hidden">

    <!-- ── Loading skeleton ─────────────────────────────────────────────── -->
    <div v-if="loading" class="flex h-full items-center justify-center bg-[#f8f9fb]">
      <div class="space-y-4 w-full max-w-sm px-8">
        <div class="joinotify-skeleton h-16 w-full rounded-xl bg-slate-200/70" />
        <div class="joinotify-skeleton h-10 w-2/3 mx-auto rounded-xl bg-slate-200/60" />
        <div class="joinotify-skeleton h-16 w-full rounded-xl bg-slate-200/70" />
        <div class="joinotify-skeleton h-16 w-full rounded-xl bg-slate-200/70" />
      </div>
    </div>

    <!-- ── Empty state (no trigger set yet) ───────────────────────────── -->
    <div
      v-else-if="!triggerNode"
      class="flex h-full items-center justify-center bg-[#f8f9fb]"
    >
      <div class="max-w-md px-6 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
          <i class="bx bx-git-repo-forked text-slate-400" style="font-size: 28px;" />
        </div>
        <h3 class="text-xl font-semibold tracking-tight text-slate-900">
          {{ __('Canvas vazio', textDomain) }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ __('Crie ou importe um fluxo, escolha um acionamento e comece a adicionar ações.', textDomain) }}
        </p>
      </div>
    </div>

    <!-- ── Main FlowCanvas ─────────────────────────────────────────────── -->
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
      @select-action="$emit('select-action', $event)"
      @update-node="$emit('update-node', $event)"
    />

    <!-- ── Node Settings Drawer ────────────────────────────────────────── -->
    <NodeSettingsDrawer
      :open="drawerOpen"
      :node="selectedNode"
      :contexts="contexts"
      @close="$emit('close-drawer')"
      @update="$emit('update-node', $event)"
    />

    <!-- ── Action Library Sidebar ─────────────────────────────────────── -->
    <BuilderActionSidebar
      :open="actionsOpen"
      :actions="actions"
      :loading="actionsLoading"
      :context="String(triggerNode?.data?.context ?? '')"
      @close="$emit('close-actions')"
      @select="$emit('select-action', $event)"
    />
  </div>
</template>
