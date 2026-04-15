<script setup lang="ts">
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BuilderCanvas from './BuilderCanvas.vue';
import NodeConnector from '../nodes/NodeConnector.vue';
import TriggerNode from '../nodes/TriggerNode.vue';
import AddNodeButton from '../nodes/AddNodeButton.vue';
import NodeSettingsDrawer from '../settings/NodeSettingsDrawer.vue';
import WorkflowTreeRenderer from '../nodes/WorkflowTreeRenderer.vue';
import { getTriggerDefinition } from '../../registries/triggerRegistry';

const props = defineProps({
  triggerNode: { type: Object, default: null },
  nodes: { type: Array, default: () => [] },
  selectedNodeId: { type: String, default: '' },
  selectedNode: { type: Object, default: null },
  contexts: { type: Array, default: () => [] },
  drawerOpen: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  actions: { type: Array, default: () => [] },
  actionsLoading: { type: Boolean, default: false },
  actionsOpen: { type: Boolean, default: false },
});

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

const rootFlowNodes = computed(() => {
  return Array.isArray(props.nodes)
    ? props.nodes.filter((node) => node && node.type !== 'trigger')
    : [];
});

function openActionsForTrigger() {
  if (!props.triggerNode?.id) {
    return;
  }

  emit('open-actions', { afterNodeId: props.triggerNode.id });
}
</script>

<template>
  <div class="relative h-full w-full overflow-hidden">
    <BuilderCanvas class="canvas-dot absolute inset-0 h-full w-full">
      <div class="relative h-full w-full overflow-hidden">
        <div class="flex h-full w-full flex-col">
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
                  <AddNodeButton label="Add action" @click="openActionsForTrigger" />
                  <NodeConnector />
                </div>
              </div>

              <div v-else-if="rootFlowNodes.length" class="w-full">
                <WorkflowTreeRenderer
                  :nodes="rootFlowNodes"
                  :selected-node-id="selectedNodeId"
                  :parent-node-id="triggerNode?.id || ''"
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
                    <AddNodeButton label="Add action" @click="openActionsForTrigger" />
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

    <div class="pointer-events-none absolute inset-y-0 right-0 z-20 flex h-full">
      <div class="pointer-events-auto h-full transition-[transform,opacity] duration-300 ease-out" :class="actionsOpen ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'">
        <aside class="ml-4 flex h-full w-[24rem] flex-col overflow-hidden rounded-l-[28px] border-l border-slate-200 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.12)]">
          <div class="flex items-start justify-between border-b border-slate-200 px-5 py-5">
            <div>
              <h2 class="text-[1.35rem] font-semibold tracking-tight text-slate-900">
                {{ __('Adicionar uma ação', textDomain) }}
              </h2>
              <p class="mt-2 max-w-[18rem] text-sm leading-6 text-slate-500">
                {{ __('Selecione uma ou mais ações para o fluxo da automação.', textDomain) }}
              </p>
            </div>

            <button
              type="button"
              class="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full border border-transparent text-2xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
              :aria-label="__('Close panel', textDomain)"
              @click="$emit('close-actions')"
            >
              x
            </button>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
            <template v-if="actionsLoading">
              <div
                v-for="index in 5"
                :key="`action-skeleton-${index}`"
                class="joinotify-skeleton mb-3 flex items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4"
              >
                <div class="joinotify-skeleton mt-0.5 h-10 w-10 rounded-full bg-slate-200/70" />
                <div class="min-w-0 flex-1">
                  <div class="joinotify-skeleton h-4 w-32 rounded-full bg-slate-200/75" />
                  <div class="joinotify-skeleton mt-3 h-3 w-40 rounded-full bg-slate-200/60" />
                  <div class="joinotify-skeleton mt-2 h-3 w-28 rounded-full bg-slate-200/60" />
                </div>
              </div>
            </template>

            <template v-else>
              <button
                v-for="action in actions"
                :key="action.action || action.id"
                type="button"
                class="mb-3 flex w-full items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-left transition hover:border-slate-300 hover:shadow-[0_12px_30px_rgba(15,23,42,0.08)]"
                @click="$emit('select-action', action.action || action.id)"
              >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-100 bg-slate-50 text-slate-500">
                  <span
                    v-if="action.iconSvg && String(action.iconSvg).trim().startsWith('<svg')"
                    class="flex h-5 w-5 items-center justify-center text-primary-700"
                    v-html="action.iconSvg"
                  />
                  <span v-else-if="action.icon" class="text-[0.72rem] font-semibold uppercase tracking-[0.18em]">
                    {{ String(action.icon).slice(0, 1) }}
                  </span>
                  <span v-else class="text-[0.72rem] font-semibold uppercase tracking-[0.18em]">
                    {{ String(action.title || action.label || 'A').slice(0, 1) }}
                  </span>
                </div>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold leading-6 text-slate-900">
                      {{ action.title || action.label }}
                    </h3>
                  </div>
                  <p class="mt-1 text-sm leading-6 text-slate-500">
                    {{ action.description }}
                  </p>
                </div>
              </button>

              <div v-if="!actions.length" class="rounded-[14px] border border-dashed border-slate-300 px-4 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">{{ __('Nenhuma ação disponível.', textDomain) }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-500">{{ __('Verifique a configuração do backend ou o contexto do gatilho selecionado.', textDomain) }}</p>
              </div>
            </template>
          </div>
        </aside>
      </div>
    </div>
  </div>
</template>
