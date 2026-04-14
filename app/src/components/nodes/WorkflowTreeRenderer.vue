<script setup lang="ts">
import NodeBranch from './NodeBranch.vue';

defineOptions({ name: 'WorkflowTreeRenderer' });

defineProps({
  nodes: { type: Array, default: () => [] },
  selectedNodeId: { type: String, default: '' },
  parentNodeId: { type: String, default: '' },
  branchKey: { type: String, default: '' },
  branchLabel: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
});

defineEmits(['select-node', 'open-actions', 'duplicate-node', 'remove-node', 'move-node']);
</script>

<template>
  <NodeBranch
    :nodes="nodes"
    :selected-node-id="selectedNodeId"
    :parent-node-id="parentNodeId"
    :branch-key="branchKey"
    :branch-label="branchLabel"
    :empty-label="emptyLabel"
    @select-node="$emit('select-node', $event)"
    @open-actions="$emit('open-actions', $event)"
    @duplicate-node="$emit('duplicate-node', $event)"
    @remove-node="$emit('remove-node', $event)"
    @move-node="$emit('move-node', $event)"
  />
</template>
