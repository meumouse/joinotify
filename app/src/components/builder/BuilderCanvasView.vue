<script setup lang="ts">
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BuilderCanvas from './BuilderCanvas.vue';
import NodeConnector from '../nodes/NodeConnector.vue';
import TriggerNode from '../nodes/TriggerNode.vue';
import AddNodeButton from '../nodes/AddNodeButton.vue';
import NodeSettingsDrawer from '../settings/NodeSettingsDrawer.vue';
import WorkflowTreeRenderer from '../nodes/WorkflowTreeRenderer.vue';
import BuilderActionSidebar from './BuilderActionSidebar.vue';
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
  actionsLoading: boolean;
  actionsOpen: boolean;
}

const props = defineProps<BuilderCanvasProps>();

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

const triggerDefinition = computed(() => {
  if (!props.triggerNode) {
    return undefined;
  }

  return getTriggerDefinition(
    String(props.triggerNode.data?.context || ''),
    String(props.triggerNode.data?.trigger || '')
  );
});

const triggerContextIconSvg = computed(() => {
  const contextId = String(props.triggerNode?.data?.context || '').trim();

  if (!contextId) {
    return '';
  }

  const context = Array.isArray(props.contexts)
    ? props.contexts.find((item) => item.id === contextId)
    : null;

  return String(context?.icon_svg || '').trim();
});

const rootFlowNodes = computed(() => {
  return Array.isArray(props.nodes)
    ? props.nodes.filter((node) => node.type !== 'trigger')
    : [];
});

function openActionsForTrigger() {
  if (!props.triggerNode?.id) {
    return;
  }

  emit('open-actions', { afterNodeId: props.triggerNode.id });
}

function getTriggerAddButtonId() {
  return props.triggerNode?.id ? `joinotify-add-action-after-${props.triggerNode.id}` : 'joinotify-add-action-after-trigger';
}
</script>

<template>
  <div class="relative h-full min-h-0 w-full overflow-hidden">
    <BuilderCanvas class="canvas-dot absolute inset-0 h-full w-full">
      <div class="relative h-full min-h-0 w-full overflow-hidden">
        <div class="flex h-full min-h-0 w-full flex-col">
          <div class="flex-1 overflow-y-auto px-4 pb-12 pt-6 lg:px-8">
            <div class="mx-auto flex w-full max-w-[1280px] flex-col items-center">
              <div v-if="loading" class="w-full max-w-[920px]">
                <div class="w-full rounded-[28px] border border-slate-200 bg-white/80 p-5 shadow-[0_12px_40px_rgba(15,23,42,0.04)]">
                  <div class="flex items-start gap-4">
                    <div class="joinotify-skeleton h-14 w-14 rounded-2xl bg-slate-200/70" />
                    <div class="min-w-0 flex-1 pt-1">
                      <div class="joinotify-skeleton h-5 w-40 rounded-full bg-slate-200/75" />
                      <div class="joinotify-skeleton mt-3 h-4 w-72 rounded-full bg-slate-200/60" />
                      <div class="joinotify-skeleton mt-2 h-4 w-56 rounded-full bg-slate-200/60" />
                    </div>
                  </div>
                </div>

                <div class="mt-6 flex flex-col items-center">
                  <div class="joinotify-skeleton h-10 w-28 rounded-full bg-slate-200/70" />
                  <div class="joinotify-skeleton mt-5 h-11 w-32 rounded-[12px] bg-slate-200/70" />
                  <div class="joinotify-skeleton mt-5 h-10 w-28 rounded-full bg-slate-200/70" />
                </div>

                <div class="mt-6 space-y-4">
                  <div
                    v-for="index in 4"
                    :key="`workflow-card-skeleton-${index}`"
                    class="flex w-full items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
                  >
                    <div class="joinotify-skeleton h-10 w-10 rounded-full bg-slate-200/70" />
                    <div class="min-w-0 flex-1">
                      <div class="joinotify-skeleton h-4 w-36 rounded-full bg-slate-200/75" />
                      <div class="joinotify-skeleton mt-3 h-3 w-full rounded-full bg-slate-200/60" />
                      <div class="joinotify-skeleton mt-2 h-3 w-5/6 rounded-full bg-slate-200/60" />
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="triggerNode" class="w-full max-w-[920px]">
                <TriggerNode
                  :title="String(triggerDefinition?.label || triggerNode.data?.title || __('Trigger', textDomain))"
                :description="String(triggerDefinition?.description || triggerNode.data?.description || __('Create a trigger to start the workflow.', textDomain))"
                :context="String(triggerNode.data?.context || '')"
                :trigger="String(triggerNode.data?.trigger || '')"
                :context-icon-svg="triggerContextIconSvg"
                :icon="String(triggerDefinition?.icon || triggerNode.data?.icon || '')"
                :icon-svg="String(triggerDefinition?.iconSvg || triggerNode.data?.iconSvg || triggerNode.data?.icon_svg || '')"
                :settings-schema="triggerDefinition?.schema || []"
                  :settings-component="String(triggerDefinition?.settingsComponent || '')"
                  :require-settings="Boolean(triggerDefinition?.requireSettings)"
                  :active="selectedNodeId === triggerNode.id"
                  @click="$emit('select-node', triggerNode.id)"
                />

                <div class="flex flex-col items-center">
                  <NodeConnector branch-label="Start" />
                  <AddNodeButton
                    :button-id="getTriggerAddButtonId()"
                    :aria-label="__('Add action after trigger', textDomain)"
                    label="Add action"
                    @click="openActionsForTrigger"
                  />
                  <NodeConnector />
                </div>
              </div>

              <div v-if="triggerNode && rootFlowNodes.length" class="mt-8 w-full">
                <WorkflowTreeRenderer
                  :nodes="rootFlowNodes"
                  :selected-node-id="selectedNodeId"
                  :parent-node-id="triggerNode.id"
                  empty-label="Add the next action"
                  @select-node="$emit('select-node', $event)"
                  @open-actions="$emit('open-actions', $event)"
                  @duplicate-node="$emit('duplicate-node', $event)"
                  @remove-node="$emit('remove-node', $event)"
                  @move-node="$emit('move-node', $event)"
                />
              </div>

              <div
                v-else-if="triggerNode"
                class="mt-4 flex min-h-[220px] w-full max-w-[920px] items-center justify-center rounded-[32px] border border-dashed border-slate-300 bg-white/70 px-6 py-10 text-center shadow-[0_12px_40px_rgba(15,23,42,0.04)]"
              >
                <div class="max-w-md">
                  <h3 class="text-xl font-semibold tracking-tight text-slate-900">
                    {{ __('Add the first action', textDomain) }}
                  </h3>
                  <p class="mt-2 text-sm leading-6 text-slate-500">
                    {{ __('Pick an action from the side panel to continue the workflow after the trigger.', textDomain) }}
                  </p>
                  <div class="mt-6 flex justify-center">
                    <AddNodeButton
                      :button-id="getTriggerAddButtonId()"
                      :aria-label="__('Add action after trigger', textDomain)"
                      label="Add action"
                      @click="openActionsForTrigger"
                    />
                  </div>
                </div>
              </div>

              <div
                v-else
                class="mx-auto flex min-h-[420px] w-full max-w-[920px] items-center justify-center rounded-[36px] border border-dashed border-slate-300 bg-white/75 px-8 py-12 text-center shadow-[0_12px_40px_rgba(15,23,42,0.04)]"
              >
                <div class="max-w-lg">
                  <h3 class="text-2xl font-semibold tracking-tight text-slate-900">
                    {{ __('Empty canvas', textDomain) }}
                  </h3>
                  <p class="mt-3 text-sm leading-6 text-slate-500">
                    {{ __('Create or import a workflow, then choose a trigger and start adding actions.', textDomain) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <NodeSettingsDrawer
            :open="drawerOpen"
            :node="selectedNode"
            :contexts="contexts"
            @close="$emit('close-drawer')"
            @update="$emit('update-node', $event)"
          />
        </div>
      </div>
    </BuilderCanvas>

    <BuilderActionSidebar
      :open="actionsOpen"
      :actions="actions"
      :loading="actionsLoading"
      :context="String(triggerNode?.data?.context || '')"
      @close="$emit('close-actions')"
      @select="$emit('select-action', $event)"
    />
  </div>
</template>
