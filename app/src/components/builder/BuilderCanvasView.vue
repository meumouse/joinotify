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
  flowReady: boolean;
  readyTrigger: boolean;
  readyActions: boolean;
  readySenders: boolean;
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

const flowCanvasRef = ref<FlowCanvasExpose | null>(null);
const showFlowLoader = computed(() => props.loading || !props.flowReady);
const loaderItems = computed(() => [
  {
    key: 'trigger',
    label: __('Trigger definido', textDomain),
    ready: props.readyTrigger,
  },
  {
    key: 'actions',
    label: __('Acoes carregadas', textDomain),
    ready: props.readyActions,
  },
  {
    key: 'senders',
    label: __('Remetentes disponiveis', textDomain),
    ready: props.readySenders,
  },
]);

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
          <i class="bx bx-git-repo-forked text-slate-400" style="font-size: 28px;" />
        </div>
        <h3 class="text-xl font-semibold tracking-tight text-slate-900">
          {{ __('Canvas vazio', textDomain) }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ __('Crie ou importe um fluxo, escolha um acionamento e comece a adicionar acoes.', textDomain) }}
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
      @select-action="$emit('select-action', $event)"
      @update-node="$emit('update-node', $event)"
    />

    <Transition name="flow-loader-fade">
      <div
        v-if="showFlowLoader"
        class="canvas-flow-loader absolute inset-0 z-30 flex items-center justify-center px-4"
        role="status"
        aria-live="polite"
        aria-label="Canvas flow carregando"
      >
        <div class="canvas-flow-loader__surface w-full max-w-md rounded-3xl border border-white/70 px-7 py-8 shadow-2xl">
          <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center">
            <span class="canvas-flow-loader__orbit canvas-flow-loader__orbit--outer" />
            <span class="canvas-flow-loader__orbit canvas-flow-loader__orbit--middle" />
            <span class="canvas-flow-loader__core">
              <span class="canvas-flow-loader__core-dot" />
            </span>
          </div>

          <h3 class="text-center text-[20px] font-semibold tracking-tight text-slate-900">
            {{ __('Preparando o canvas flow', textDomain) }}
          </h3>
          <p class="mt-1 text-center text-sm text-slate-600">
            {{ __('Finalizando recursos para liberar o builder.', textDomain) }}
          </p>

          <div class="mt-6 space-y-2.5">
            <div
              v-for="item in loaderItems"
              :key="item.key"
              class="flex items-center justify-between rounded-xl border px-3 py-2.5 text-sm"
              :class="item.ready ? 'border-emerald-200 bg-emerald-50/80 text-emerald-800' : 'border-slate-200 bg-white/80 text-slate-600'"
            >
              <span class="font-medium">{{ item.label }}</span>
              <span
                class="inline-flex h-6 w-6 items-center justify-center rounded-full border"
                :class="item.ready ? 'border-emerald-300 bg-emerald-100 text-emerald-700' : 'border-slate-300 text-slate-400'"
              >
                <i :class="item.ready ? 'bx bx-check' : 'bx bx-loader-alt bx-spin'" />
              </span>
            </div>
          </div>
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

<style scoped>
.canvas-flow-loader {
  background:
    radial-gradient(circle at 15% 20%, rgba(59, 130, 246, 0.16), transparent 40%),
    radial-gradient(circle at 85% 15%, rgba(16, 185, 129, 0.14), transparent 44%),
    linear-gradient(160deg, rgba(248, 250, 252, 0.96), rgba(241, 245, 249, 0.98));
  backdrop-filter: blur(4px);
}

.canvas-flow-loader__surface {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.9));
}

.canvas-flow-loader__orbit {
  position: absolute;
  border-radius: 9999px;
  border-style: solid;
  border-color: transparent;
  border-top-color: #16a34a;
  border-right-color: rgba(37, 99, 235, 0.8);
}

.canvas-flow-loader__orbit--outer {
  width: 80px;
  height: 80px;
  border-width: 3px;
  animation: joinotify-flow-loader-spin 1.05s linear infinite;
}

.canvas-flow-loader__orbit--middle {
  width: 56px;
  height: 56px;
  border-width: 3px;
  border-top-color: #2563eb;
  border-right-color: rgba(22, 163, 74, 0.8);
  animation: joinotify-flow-loader-spin-reverse 1.35s linear infinite;
}

.canvas-flow-loader__core {
  width: 34px;
  height: 34px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #22c55e, #2563eb);
  box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25);
  animation: joinotify-flow-loader-pulse 1.2s ease-in-out infinite;
}

.canvas-flow-loader__core-dot {
  width: 12px;
  height: 12px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.9);
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

@keyframes joinotify-flow-loader-spin {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(360deg);
  }
}

@keyframes joinotify-flow-loader-spin-reverse {
  0% {
    transform: rotate(360deg);
  }

  100% {
    transform: rotate(0deg);
  }
}

@keyframes joinotify-flow-loader-pulse {
  0%,
  100% {
    transform: scale(0.95);
    box-shadow: 0 10px 22px rgba(37, 99, 235, 0.2);
  }

  50% {
    transform: scale(1.05);
    box-shadow: 0 16px 28px rgba(22, 163, 74, 0.28);
  }
}
</style>
