import { computed, ref } from 'vue';
import { useActionRegistry } from './useActionRegistry';
import {
  cloneWorkflowAction,
  createActionTreeFromList,
  deleteItemRecursive,
  extractAllActions,
  findActionById,
  insertActionAfter,
  insertActionInBranch,
  updateActionById,
} from '../utils/workflowActions';
import { toWorkflowActionItem } from '../registry/actionRegistry';
import type { WorkflowActionItem } from '../registry/types';

export function useWorkflowBuilder(initialWorkflow: WorkflowActionItem[] = []) {
  const registry = useActionRegistry();
  const workflow = ref<WorkflowActionItem[]>(createActionTreeFromList(initialWorkflow || []));
  const selectedActionId = ref('');
  const drawerOpen = ref(false);
  const editingActionId = ref('');
  const availableContext = ref('');

  const actions = computed(() => registry.byContext(availableContext.value));
  const selectedAction = computed(() => (selectedActionId.value ? findActionById(workflow.value, selectedActionId.value) : null));

  function setWorkflow(nextWorkflow: WorkflowActionItem[]) {
    workflow.value = createActionTreeFromList(nextWorkflow || []);
  }

  function addAction(actionSlug: string, afterId: string, branchKey?: string) {
    const item = toWorkflowActionItem(actionSlug, registry.defaults(actionSlug));
    return branchKey ? insertActionInBranch(workflow.value, afterId, branchKey, item) : insertActionAfter(workflow.value, afterId, item);
  }

  function editAction(id: string, patch: Record<string, unknown>) {
    return updateActionById(workflow.value, id, patch);
  }

  function deleteAction(id: string) {
    return deleteItemRecursive(workflow.value, id);
  }

  function duplicateAction(id: string) {
    const node = findActionById(workflow.value, id);

    if (!node) {
      return null;
    }

    const clone = cloneWorkflowAction(node);
    clone.id = `${clone.id}-copy-${Math.random().toString(36).slice(2, 8)}`;
    return insertActionAfter(workflow.value, id, clone);
  }

  function openDrawer(id: string) {
    editingActionId.value = id;
    selectedActionId.value = id;
    drawerOpen.value = true;
  }

  function closeDrawer() {
    drawerOpen.value = false;
    editingActionId.value = '';
  }

  function extractWorkflowActions() {
    return extractAllActions(workflow.value);
  }

  return {
    workflow,
    actions,
    selectedActionId,
    selectedAction,
    drawerOpen,
    editingActionId,
    availableContext,
    setWorkflow,
    addAction,
    editAction,
    deleteAction,
    duplicateAction,
    openDrawer,
    closeDrawer,
    extractWorkflowActions,
    findActionById: (id: string) => findActionById(workflow.value, id),
  };
}
