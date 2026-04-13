<script setup>
import { __, textDomain } from '../../utils/i18n';
import ActionNode from './ActionNode.vue';
import AddNodeButton from './AddNodeButton.vue';
import NodeConnector from './NodeConnector.vue';
import TriggerNode from './TriggerNode.vue';

defineOptions({ name: 'WorkflowTreeRenderer' });

defineProps({
  nodes: { type: Array, default: () => [] },
  selectedNodeId: { type: String, default: '' },
});

defineEmits(['select-node', 'add-node', 'duplicate-node', 'remove-node', 'open-actions']);

function labelForNode(node) {
  if (node.type === 'trigger') {
    return node.data?.title || __('Trigger', textDomain);
  }

  return node.data?.title || node.data?.action || __('Action', textDomain);
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-4xl flex-col py-8">
    <template v-for="(node, index) in nodes" :key="node.id">
      <div class="flex flex-col items-center">
        <TriggerNode
          v-if="node.type === 'trigger'"
          :title="labelForNode(node)"
          :description="node.data?.description || ''"
          :context="node.data?.context || ''"
          :trigger="node.data?.trigger || ''"
          :active="selectedNodeId === node.id"
          @click="$emit('select-node', node.id)"
        />
        <ActionNode
          v-else
          :title="labelForNode(node)"
          :description="node.data?.description || node.data?.message || ''"
          :action="node.data?.action || ''"
          :active="selectedNodeId === node.id"
          @click="$emit('select-node', node.id)"
        />

        <div class="mt-3 flex flex-col items-center">
          <AddNodeButton :label="__('Add action', textDomain)" @click="$emit('open-actions', node.id)" />
          <NodeConnector v-if="index < nodes.length - 1 || (Array.isArray(node.children) && node.children.length)" />
        </div>

        <div v-if="Array.isArray(node.children) && node.children.length" class="mt-2 w-full border-l border-dashed border-slate-300 pl-8">
          <WorkflowTreeRenderer
            :nodes="node.children"
            :selected-node-id="selectedNodeId"
            @select-node="$emit('select-node', $event)"
            @add-node="$emit('add-node', $event)"
            @duplicate-node="$emit('duplicate-node', $event)"
            @remove-node="$emit('remove-node', $event)"
            @open-actions="$emit('open-actions', $event)"
          />
        </div>
      </div>
    </template>

    <div v-if="!nodes.length" class="mt-4 flex justify-center">
      <AddNodeButton :label="__('Add action', textDomain)" @click="$emit('open-actions', '')" />
    </div>
  </div>
</template>
