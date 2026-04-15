import { computed, ref, unref, type MaybeRef } from 'vue';
import {
  deleteItemRecursive,
  extractAllActions,
  findActionById,
  insertActionAfter,
  insertActionInBranch,
  updateActionById,
} from '../utils/workflowActions';
import type { WorkflowActionItem } from '../registry/types';

export function useBuilderActions(workflow: MaybeRef<WorkflowActionItem[]>) {
  const selectedActionId = ref('');

  const workflowRef = computed(() => unref(workflow) || []);

  function find(id: string) {
    return findActionById(workflowRef.value, id);
  }

  function update(id: string, patch: Record<string, unknown>) {
    return updateActionById(workflowRef.value, id, patch);
  }

  function remove(id: string) {
    return deleteItemRecursive(workflowRef.value, id);
  }

  function insertAfter(anchorId: string, nextItem: WorkflowActionItem) {
    return insertActionAfter(workflowRef.value, anchorId, nextItem);
  }

  function insertInBranch(parentId: string, branchKey: string, nextItem: WorkflowActionItem) {
    return insertActionInBranch(workflowRef.value, parentId, branchKey, nextItem);
  }

  function all() {
    return extractAllActions(workflowRef.value);
  }

  return {
    workflow: workflowRef,
    selectedActionId,
    find,
    update,
    remove,
    insertAfter,
    insertInBranch,
    all,
  };
}
