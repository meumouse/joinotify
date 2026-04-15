import { cloneSerializable } from '../../../utils/workflowTree';
import type { WorkflowActionItem } from '../registry/types';

export function cloneWorkflowAction(item: WorkflowActionItem): WorkflowActionItem {
  return cloneSerializable(item);
}

export function ensureActionChildren(item: WorkflowActionItem): Record<string, WorkflowActionItem[]> {
  if (!item.children || typeof item.children !== 'object') {
    item.children = {};
  }

  return item.children;
}

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

export function extractAllActions(tree: WorkflowActionItem[]): WorkflowActionItem[] {
  const flattened: WorkflowActionItem[] = [];

  walkActionTree(tree, (item) => {
    flattened.push(cloneWorkflowAction(item));
  });

  return flattened;
}

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

export function createActionTreeFromList(items: WorkflowActionItem[] = []): WorkflowActionItem[] {
  return items.map((item) => cloneWorkflowAction(item));
}
