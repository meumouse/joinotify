<script setup lang="ts">
import { getActionDefinition, getActionRegistryPreview } from '../../registries/actionRegistry';
import { getTriggerDefinitionFromNode } from '../../registries/triggerRegistry';
import type { WorkflowBranchKey, WorkflowNode } from '../../types/workflowBuilder';
import { isConditionNode, isDelayNode, isPlaceholderNode, isSnippetNode, isStopNode } from '../../utils/workflowTree';
import AddNodeButton from './AddNodeButton.vue';
import ActionNode from './ActionNode.vue';
import ConditionNode from './ConditionNode.vue';
import DelayNode from './DelayNode.vue';
import NodeConnector from './NodeConnector.vue';
import NodeToolbar from './NodeToolbar.vue';
import PlaceholderNode from './PlaceholderNode.vue';
import SnippetNode from './SnippetNode.vue';
import StopNode from './StopNode.vue';
import TriggerNode from './TriggerNode.vue';

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

function branchTarget(afterNodeId: string, branchKey?: WorkflowBranchKey) {
  emit('open-actions', {
    afterNodeId,
    branchKey,
  });
}

function resolveNodeDefinition(node: WorkflowNode) {
  if (node.type === 'trigger') {
    return getTriggerDefinitionFromNode(node) || undefined;
  }

  return getActionDefinition(String(node.data.action || '')) || undefined;
}

function resolveNodePreview(node: WorkflowNode) {
  const definition = resolveNodeDefinition(node);

  if (node.type === 'trigger' && definition?.preview) {
    return definition.preview(node.data);
  }

  if (definition?.preview) {
    return definition.preview(node.data);
  }

  return getActionRegistryPreview(node);
}

function resolveNodeComponent(node: WorkflowNode) {
  if (node.type === 'trigger') {
    return TriggerNode;
  }

  if (isConditionNode(node)) {
    return ConditionNode;
  }

  if (isDelayNode(node)) {
    return DelayNode;
  }

  if (isStopNode(node)) {
    return StopNode;
  }

  if (isSnippetNode(node)) {
    return SnippetNode;
  }

  if (isPlaceholderNode(node)) {
    return PlaceholderNode;
  }

  return ActionNode;
}

function resolveNodeAccent(node: WorkflowNode): string {
  if (node.type === 'trigger') {
    return 'blue';
  }

  if (isConditionNode(node)) {
    return 'violet';
  }

  if (isDelayNode(node)) {
    return 'amber';
  }

  if (isStopNode(node)) {
    return 'rose';
  }

  if (isSnippetNode(node)) {
    return 'violet';
  }

  if (isPlaceholderNode(node)) {
    return 'emerald';
  }

  return 'slate';
}

function resolveNodeBadge(node: WorkflowNode): string {
  if (node.type === 'trigger') {
    return 'Trigger';
  }

  if (isConditionNode(node)) {
    return 'Condition';
  }

  if (isDelayNode(node)) {
    return 'Delay';
  }

  if (isStopNode(node)) {
    return 'Stop';
  }

  if (isSnippetNode(node)) {
    return 'Snippet';
  }

  if (isPlaceholderNode(node)) {
    return 'Placeholder';
  }

  return 'Action';
}

function resolveNodeTitle(node: WorkflowNode): string {
  return String(node.data?.title || resolveNodeDefinition(node)?.label || resolveNodeBadge(node));
}

function resolveNodeDescription(node: WorkflowNode): string {
  const description = node.type === 'trigger'
    ? String(node.data?.description || '')
    : String(node.data?.description || node.data?.message || resolveNodePreview(node) || '');

  return description;
}

function hasBranchChildren(node: WorkflowNode): boolean {
  return Array.isArray(node.children) && node.children.length > 0;
}

function hasConditionBranches(node: WorkflowNode): boolean {
  return isConditionNode(node) && Boolean(node.branches) && (
    (node.branches?.action_true || []).length > 0 ||
    (node.branches?.action_false || []).length > 0
  );
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

          <component
            :is="resolveNodeComponent(node)"
            class="w-full"
            :title="resolveNodeTitle(node)"
            :description="resolveNodeDescription(node)"
            :action="String(node.data?.action || '')"
            :condition="String(node.data?.condition || '')"
            :operator="String(node.data?.condition_type || '')"
            :preview="resolveNodePreview(node)"
            :badge="resolveNodeBadge(node)"
            :icon="resolveNodeDefinition(node)?.icon || ''"
            :icon-svg="resolveNodeDefinition(node)?.iconSvg || ''"
            :accent="resolveNodeAccent(node)"
            :context="String(node.data?.context || '')"
            :trigger="String(node.data?.trigger || '')"
            :active="selectedNodeId === node.id"
            @click="$emit('select-node', node.id)"
          />
        </div>

        <NodeConnector v-if="index < nodes.length - 1 || !hasConditionBranches(node)" />

        <div class="flex flex-col items-center" :class="index < nodes.length - 1 ? 'pb-2' : 'pb-4'">
          <AddNodeButton
            :label="isConditionNode(node) ? 'Add to flow' : 'Add action'"
            @click="branchTarget(node.id, branchKey || undefined)"
          />
        </div>

        <div v-if="hasBranchChildren(node)" class="mt-6 w-full pl-6 lg:pl-10">
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
          <section class="rounded-[28px] border border-emerald-200 bg-emerald-50/50 p-4 shadow-[0_10px_30px_rgba(16,185,129,0.08)]">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">True branch</p>
                <p class="mt-1 text-sm text-emerald-900/70">Nodes that continue when the condition matches.</p>
              </div>
              <span class="rounded-full bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-emerald-700">action_true</span>
            </div>

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
          </section>

          <section class="rounded-[28px] border border-rose-200 bg-rose-50/50 p-4 shadow-[0_10px_30px_rgba(244,63,94,0.08)]">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">False branch</p>
                <p class="mt-1 text-sm text-rose-900/70">Nodes that continue when the condition does not match.</p>
              </div>
              <span class="rounded-full bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-rose-700">action_false</span>
            </div>

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
          </section>
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
