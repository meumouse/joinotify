<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';
import BuilderCanvas from './BuilderCanvas.vue';
import NodeSettingsDrawer from '../settings/NodeSettingsDrawer.vue';
import WorkflowTreeRenderer from '../nodes/WorkflowTreeRenderer.vue';

defineProps({
  nodes: { type: Array, default: () => [] },
  selectedNodeId: { type: String, default: '' },
  selectedNode: { type: Object, default: null },
  contexts: { type: Array, default: () => [] },
  drawerOpen: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

defineEmits(['select-node', 'add-node', 'duplicate-node', 'remove-node', 'open-actions', 'update-node', 'close-drawer', 'test', 'export']);
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-soft">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
      <div>
        <p class="text-sm font-semibold text-slate-900">{{ __('Canvas', textDomain) }}</p>
        <p class="text-xs text-slate-500">{{ __('Declarative timeline rendered from `workflow_content`.', textDomain) }}</p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton :title="__('Run test', textDomain)" variant="secondary" :loading="loading" @click="$emit('test')" />
        <BaseButton :title="__('Export JSON', textDomain)" variant="secondary" @click="$emit('export')" />
      </div>
    </div>

    <BuilderCanvas>
      <div class="min-h-[840px] px-5 py-8">
        <div v-if="nodes.length">
          <WorkflowTreeRenderer
            :nodes="nodes"
            :selected-node-id="selectedNodeId"
            @select-node="$emit('select-node', $event)"
            @add-node="$emit('add-node', $event)"
            @duplicate-node="$emit('duplicate-node', $event)"
            @remove-node="$emit('remove-node', $event)"
            @open-actions="$emit('open-actions', $event)"
          />
        </div>
        <div v-else class="flex min-h-[760px] items-center justify-center rounded-[28px] border border-dashed border-slate-300 bg-white/70 text-center">
          <div class="max-w-md">
            <h3 class="text-xl font-semibold text-slate-900">{{ __('Empty canvas', textDomain) }}</h3>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Create a trigger to start the workflow, then add actions.', textDomain) }}</p>
          </div>
        </div>
      </div>
    </BuilderCanvas>

    <NodeSettingsDrawer
      :open="drawerOpen"
      :node="selectedNode"
      :contexts="contexts"
      @close="$emit('close-drawer')"
      @update="$emit('update-node', $event)"
    />
  </div>
</template>
