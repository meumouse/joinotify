/**
 * workflowActions.ts
 *
 * Pure tree-manipulation helpers for the workflow action list. Provides cloning,
 * lookup, traversal, insertion and deletion over the nested action tree, where
 * branching actions hold child actions keyed by branch.
 *
 * @since 2.0.0
 */
import { cloneSerializable } from '../../../utils/workflowTree';
import type { WorkflowActionItem } from '../registry/types';

/**
 * Deep-clone a workflow action into a plain serializable copy.
 *
 * @since 2.0.0
 * @param {WorkflowActionItem} item Action to clone.
 * @returns {WorkflowActionItem} Cloned action.
 */
export function cloneWorkflowAction(item: WorkflowActionItem): WorkflowActionItem {
  return cloneSerializable(item);
}

/**
 * Ensure the action has a children map, creating an empty one when missing.
 *
 * @since 2.0.0
 * @param {WorkflowActionItem} item Action whose children map is needed.
 * @returns {Object} The action's children map keyed by branch.
 */
export function ensureActionChildren(item: WorkflowActionItem): Record<string, WorkflowActionItem[]> {
  if (!item.children || typeof item.children !== 'object') {
    item.children = {};
  }

  return item.children;
}

/**
 * Recursively find an action by id anywhere in the tree.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to search.
 * @param {string} id Action identifier to find.
 * @returns {WorkflowActionItem|null} Matched action, or null when not found.
 */
export function findActionById(tree: WorkflowActionItem[], id: string): WorkflowActionItem | null {
  if (!Array.isArray(tree) || !id) {
    return null;
  }

  for (const item of tree) {
    if (item.id === id) {
      return item;
    }

    const children = item.children && typeof item.children === 'object' ? item.children : {};
    for (const branch of Object.values(children)) {
      const match = findActionById(branch || [], id);
      if (match) {
        return match;
      }
    }
  }

  return null;
}

/**
 * Depth-first traverse the action tree, invoking the visitor for every action.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to walk.
 * @param {Function} visitor Callback receiving the action, parent, branch key, index and list.
 * @param {WorkflowActionItem|null} parent Parent action of the current level.
 * @param {string|null} branchKey Branch key under the parent, if any.
 * @returns {void}
 */
export function walkActionTree(
  tree: WorkflowActionItem[],
  visitor: (item: WorkflowActionItem, parent: WorkflowActionItem | null, branchKey: string | null, index: number, list: WorkflowActionItem[]) => void,
  parent: WorkflowActionItem | null = null,
  branchKey: string | null = null
): void {
  tree.forEach((item, index) => {
    visitor(item, parent, branchKey, index, tree);

    const children = item.children && typeof item.children === 'object' ? item.children : {};

    for (const [key, branchItems] of Object.entries(children)) {
      walkActionTree(branchItems || [], visitor, item, key);
    }
  });
}

/**
 * Merge a data patch into the action with the given id.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to search.
 * @param {string} id Identifier of the action to update.
 * @param {Object} patch Partial data to merge into the action.
 * @returns {boolean} True when the action was found and updated.
 */
export function updateActionById(tree: WorkflowActionItem[], id: string, patch: Record<string, unknown>): boolean {
  const item = findActionById(tree, id);

  if (!item) {
    return false;
  }

  item.data = {
    ...cloneSerializable(item.data),
    ...cloneSerializable(patch),
  };

  return true;
}

/**
 * Recursively remove the action with the given id from the tree.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to mutate.
 * @param {string} id Identifier of the action to delete.
 * @returns {boolean} True when the action was found and removed.
 */
export function deleteItemRecursive(tree: WorkflowActionItem[], id: string): boolean {
  for (let index = 0; index < tree.length; index += 1) {
    const item = tree[index];

    if (item.id === id) {
      tree.splice(index, 1);
      return true;
    }

    const children = item.children && typeof item.children === 'object' ? item.children : {};
    for (const [branchKey, branchItems] of Object.entries(children)) {
      if (deleteItemRecursive(branchItems || [], id)) {
        item.children = {
          ...children,
          [branchKey]: branchItems || [],
        };
        return true;
      }
    }
  }

  return false;
}

/**
 * Flatten the action tree into a list of cloned actions.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to flatten.
 * @returns {Array} Flat list of cloned actions.
 */
export function extractAllActions(tree: WorkflowActionItem[]): WorkflowActionItem[] {
  const flattened: WorkflowActionItem[] = [];

  walkActionTree(tree, (item) => {
    flattened.push(cloneWorkflowAction(item));
  });

  return flattened;
}

/**
 * Insert an action immediately after the anchor action, searching all branches.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to mutate.
 * @param {string} anchorId Identifier of the action to insert after.
 * @param {WorkflowActionItem} nextItem Action to insert.
 * @returns {WorkflowActionItem|null} The inserted action, or null when the anchor was not found.
 */
export function insertActionAfter(
  tree: WorkflowActionItem[],
  anchorId: string,
  nextItem: WorkflowActionItem
): WorkflowActionItem | null {
  for (let index = 0; index < tree.length; index += 1) {
    const item = tree[index];

    if (item.id === anchorId) {
      tree.splice(index + 1, 0, nextItem);
      return nextItem;
    }

    const children = item.children && typeof item.children === 'object' ? item.children : {};
    for (const branchItems of Object.values(children)) {
      const inserted = insertActionAfter(branchItems || [], anchorId, nextItem);
      if (inserted) {
        return inserted;
      }
    }
  }

  return null;
}

/**
 * Append an action to a named branch of the parent action.
 *
 * @since 2.0.0
 * @param {Array} tree Action tree to search.
 * @param {string} parentId Identifier of the parent (branching) action.
 * @param {string} branchKey Branch key to append into.
 * @param {WorkflowActionItem} nextItem Action to append.
 * @returns {WorkflowActionItem|null} The appended action, or null when the parent was not found.
 */
export function insertActionInBranch(
  tree: WorkflowActionItem[],
  parentId: string,
  branchKey: string,
  nextItem: WorkflowActionItem
): WorkflowActionItem | null {
  const parent = findActionById(tree, parentId);

  if (!parent) {
    return null;
  }

  const children = ensureActionChildren(parent);
  if (!Array.isArray(children[branchKey])) {
    children[branchKey] = [];
  }

  children[branchKey].push(nextItem);
  return nextItem;
}

/**
 * Build a fresh action tree by cloning each item in the provided list.
 *
 * @since 2.0.0
 * @param {Array} items Source action list.
 * @returns {Array} New tree of cloned actions.
 */
export function createActionTreeFromList(items: WorkflowActionItem[] = []): WorkflowActionItem[] {
  return items.map((item) => cloneWorkflowAction(item));
}
