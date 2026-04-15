<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../../builder/actions/composables/useActionRegistry';
import ConditionBranchNode from '../../builder/actions/components/ConditionBranchNode.vue';
import WorkflowActionCard from '../../builder/actions/components/WorkflowActionCard.vue';
import WorkflowConnector from '../../builder/actions/components/WorkflowConnector.vue';
import AddNodeButton from './AddNodeButton.vue';
import NodeToolbar from './NodeToolbar.vue';
import type { WorkflowNode } from '../../types/workflowBuilder';

defineOptions({ name: 'NodeBranch' });

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  selectedNodeId: { type: String, default: '' },
  parentNodeId: { type: String, default: '' },
  branchKey: { type: String, default: '' },
  branchLabel: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
});

const emit = defineEmits([
  'select-node',
  'open-actions',
  'duplicate-node',
  'remove-node',
  'move-node',
]);

const registry = useActionRegistry();

function branchTarget(afterNodeId: string, branchKey?: string) {
  emit('open-actions', {
    afterNodeId,
    branchKey,
  });
}

function isConditionNode(node: WorkflowNode): boolean {
  const action = String(node.data?.action || '');
  const definition = registry.get(action);
  return action === 'condition' || Boolean(definition?.isExpansible && Array.isArray(definition.branchKeys) && definition.branchKeys.length);
}

function resolveDefinition(node: WorkflowNode) {
  const action = String(node.data?.action || '');
  return registry.get(action);
}

function hasChildren(node: WorkflowNode): boolean {
  return Array.isArray(node.children) && node.children.length > 0;
}

function hasConditionBranches(node: WorkflowNode): boolean {
  return Boolean(node.branches && (node.branches.action_true?.length || node.branches.action_false?.length));
}
</script>

<template>
  <div class="flex w-full flex-col items-center">
    <div v-if="branchLabel" class="mb-4 inline-flex rounded-full border border-slate-200 bg-white px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500 shadow-[0_1px_4px_rgba(15,23,42,0.03)]">
      {{ branchLabel }}
    </div>

    <template v-if="Array.isArray(nodes) && nodes.length">
      <div v-for="(node, index) in nodes" :key="node.id" class="flex w-full flex-col items-center">
        <div class="relative w-full">
          <div class="absolute right-4 top-4 z-10">
            <NodeToolbar
              :can-move-up="index > 0"
              :can-move-down="index < nodes.length - 1"
              :can-add-below="!isConditionNode(node)"
              :can-add-true="isConditionNode(node)"
              :can-add-false="isConditionNode(node)"
              @edit="$emit('select-node', node.id)"
              @duplicate="$emit('duplicate-node', node.id)"
              @remove="$emit('remove-node', node.id)"
              @move-up="$emit('move-node', { nodeId: node.id, direction: 'up' })"
              @move-down="$emit('move-node', { nodeId: node.id, direction: 'down' })"
              @add-below="branchTarget(node.id)"
              @add-true="branchTarget(node.id, 'action_true')"
              @add-false="branchTarget(node.id, 'action_false')"
            />
          </div>

          <WorkflowActionCard
            class="w-full"
            :action="String(node.data?.action || '')"
            :data="node.data || {}"
            :definition="resolveDefinition(node)"
            :active="selectedNodeId === node.id"
            @edit="$emit('select-node', node.id)"
            @duplicate="$emit('duplicate-node', node.id)"
            @delete="$emit('remove-node', node.id)"
            @expand="branchTarget(node.id)"
          />
        </div>

        <WorkflowConnector v-if="index < nodes.length - 1 || !hasConditionBranches(node)" />

        <div class="flex flex-col items-center" :class="index < nodes.length - 1 ? 'pb-2' : 'pb-4'">
          <AddNodeButton
            :label="isConditionNode(node) ? 'Add to flow' : 'Add action'"
            @click="branchTarget(node.id, branchKey || undefined)"
          />
        </div>

        <div v-if="hasChildren(node)" class="mt-6 w-full pl-6 lg:pl-10">
          <NodeBranch
            :nodes="node.children"
            :selected-node-id="selectedNodeId"
            :parent-node-id="node.id"
            :branch-key="''"
            branch-label="Children"
            empty-label="Add action to nested flow"
            @select-node="$emit('select-node', $event)"
            @open-actions="$emit('open-actions', $event)"
            @duplicate-node="$emit('duplicate-node', $event)"
            @remove-node="$emit('remove-node', $event)"
            @move-node="$emit('move-node', $event)"
          />
        </div>

        <div v-if="isConditionNode(node)" class="mt-6 grid w-full gap-6 xl:grid-cols-2">
          <ConditionBranchNode
            branch-key="action_true"
            branch-label="True branch"
            empty-label="Nodes that continue when the condition matches."
            tone="emerald"
          >
            <NodeBranch
              :nodes="node.branches?.action_true || []"
              :selected-node-id="selectedNodeId"
              :parent-node-id="node.id"
              branch-key="action_true"
              empty-label="Add action to the true branch"
              @select-node="$emit('select-node', $event)"
              @open-actions="$emit('open-actions', $event)"
              @duplicate-node="$emit('duplicate-node', $event)"
              @remove-node="$emit('remove-node', $event)"
              @move-node="$emit('move-node', $event)"
            />
          </ConditionBranchNode>

          <ConditionBranchNode
            branch-key="action_false"
            branch-label="False branch"
            empty-label="Nodes that continue when the condition does not match."
            tone="rose"
          >
            <NodeBranch
              :nodes="node.branches?.action_false || []"
              :selected-node-id="selectedNodeId"
              :parent-node-id="node.id"
              branch-key="action_false"
              empty-label="Add action to the false branch"
              @select-node="$emit('select-node', $event)"
              @open-actions="$emit('open-actions', $event)"
              @duplicate-node="$emit('duplicate-node', $event)"
              @remove-node="$emit('remove-node', $event)"
              @move-node="$emit('move-node', $event)"
            />
          </ConditionBranchNode>
        </div>
      </div>
    </template>

    <div v-else class="flex w-full flex-col items-center">
      <div class="max-w-xl rounded-[30px] border border-dashed border-slate-300 bg-white/80 px-8 py-10 text-center shadow-[0_12px_40px_rgba(15,23,42,0.04)]">
        <h3 class="text-lg font-semibold tracking-tight text-slate-900">
          {{ emptyLabel || 'Add the next action' }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ branchLabel ? `Start building the ${branchLabel.toLowerCase()} by inserting an action.` : 'Add a step to continue the workflow.' }}
        </p>

        <div class="mt-6 flex justify-center">
          <AddNodeButton
            :label="branchKey ? 'Add action' : 'Add action'"
            @click="branchTarget(parentNodeId || '')"
          />
        </div>
      </div>
    </div>
  </div>
</template>
