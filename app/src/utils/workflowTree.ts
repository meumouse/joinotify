import { createWorkflowNodeId } from './workflowIds';
import type {
  WorkflowBranchKey,
  WorkflowBranches,
  WorkflowContainerKey,
  WorkflowNode,
  WorkflowNodeLocation,
  WorkflowNodeKind,
  WorkflowRegistryItem,
} from '../types/workflowBuilder';

export function cloneSerializable<T>(value: T): T {
  return JSON.parse(JSON.stringify(value ?? null));
}

export function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

export function isConditionAction(data: Record<string, unknown> | undefined): boolean {
  return !!data && String(data.action || '') === 'condition';
}

export function isStopAction(data: Record<string, unknown> | undefined): boolean {
  return !!data && String(data.action || '') === 'stop_funnel';
}

export function isDelayAction(data: Record<string, unknown> | undefined): boolean {
  return !!data && String(data.action || '') === 'time_delay';
}

export function isSnippetAction(data: Record<string, unknown> | undefined): boolean {
  return !!data && String(data.action || '') === 'snippet_php';
}

export function isPlaceholderAction(data: Record<string, unknown> | undefined): boolean {
  return !!data && String(data.action || '') === 'dynamic_placeholder';
}

export function isWorkflowNode(value: unknown): value is WorkflowNode {
  return isRecord(value) && typeof value.id === 'string' && typeof value.type === 'string' && isRecord(value.data);
}

export function normalizeBranchKey(value: unknown): WorkflowBranchKey | undefined {
  const branchKey = String(value || '').trim();

  if (branchKey === 'action_true' || branchKey === 'action_false') {
    return branchKey;
  }

  return undefined;
}

export function createEmptyBranches(): WorkflowBranches {
  return {
    action_true: [],
    action_false: [],
  };
}

export function ensureNodeDefaults(node: Partial<WorkflowNode> & { type: WorkflowNodeKind }): WorkflowNode {
  const children = Array.isArray(node.children) ? node.children : [];
  const result: WorkflowNode = {
    id: typeof node.id === 'string' && node.id ? node.id : createWorkflowNodeId(node.type),
    type: node.type,
    data: isRecord(node.data) ? cloneSerializable(node.data) : {},
    children,
  };

  if (isRecord(node.branches)) {
    result.branches = {
      action_true: Array.isArray(node.branches.action_true) ? node.branches.action_true : [],
      action_false: Array.isArray(node.branches.action_false) ? node.branches.action_false : [],
    };
  }

  if (node.branchKey === 'action_true' || node.branchKey === 'action_false') {
    result.branchKey = node.branchKey;
  }

  if (typeof node.parentId === 'string' && node.parentId) {
    result.parentId = node.parentId;
  }

  return result;
}

export function createTriggerNode(payload: Partial<Record<string, unknown>> = {}): WorkflowNode {
  return ensureNodeDefaults({
    type: 'trigger',
    id: typeof payload.id === 'string' ? payload.id : createWorkflowNodeId('trigger'),
    data: {
      title: typeof payload.title === 'string' ? payload.title : 'My automation',
      description: typeof payload.description === 'string' ? payload.description : '',
      trigger: typeof payload.trigger === 'string' ? payload.trigger : '',
      context: typeof payload.context === 'string' ? payload.context : '',
      settings: isRecord(payload.settings) ? cloneSerializable(payload.settings) : {},
    },
    children: [],
  });
}

export function createActionNode(actionId = '', payload: Partial<Record<string, unknown>> = {}, definition?: WorkflowRegistryItem | null): WorkflowNode {
  const defaults = isRecord(definition?.defaultData) ? cloneSerializable(definition?.defaultData) : {};
  // Carry the caller's action-specific fields (delay_value, delay_period,
  // date_value, coupon/ai/snippet keys, …) into baseData so the definition's
  // normalize step sees the real values. Without this, only the hardcoded keys
  // below survived and every other field silently reset to its default on save
  // (e.g. a delay amount reverting to 1). `id`/`children` are structural and
  // handled separately, so they're kept out of node data.
  const { id: _payloadId, children: _payloadChildren, ...payloadData } = payload as Record<string, unknown>;
  const baseData = {
    ...defaults,
    ...cloneSerializable(payloadData),
    title: typeof payload.title === 'string' ? payload.title : definition?.label || 'Action',
    description: typeof payload.description === 'string' ? payload.description : '',
    action: actionId,
    message: typeof payload.message === 'string' ? payload.message : '',
    sender: typeof payload.sender === 'string' ? payload.sender : '',
    receiver: typeof payload.receiver === 'string' ? payload.receiver : '',
    settings: isRecord(payload.settings) ? cloneSerializable(payload.settings) : {},
  };

  const normalize = definition?.parseData || definition?.normalizeData;
  const normalized = normalize ? normalize(baseData) : baseData;

  return ensureNodeDefaults({
    type: 'action',
    id: typeof payload.id === 'string' ? payload.id : createWorkflowNodeId('action'),
    data: normalized,
    children: [],
  });
}

export function createConditionNode(payload: Partial<Record<string, unknown>> = {}, definition?: WorkflowRegistryItem | null): WorkflowNode {
  const defaults = isRecord(definition?.defaultData) ? cloneSerializable(definition?.defaultData) : {};
  // Carry the caller's condition-specific fields (products, condition_content,
  // value, …) into baseData so the definition's normalize step keeps them.
  // Without this, only the hardcoded keys below survived and the products
  // selection was dropped on save, so a "products_purchased" condition matched
  // against an empty list and always took the false branch. `id`/`children`/
  // `branches` are structural and handled separately.
  const { id: _id, children: _children, branches: _branches, ...payloadData } = payload as Record<string, unknown>;
  const baseData = {
    ...defaults,
    ...cloneSerializable(payloadData),
    title: typeof payload.title === 'string' ? payload.title : definition?.label || 'Condition',
    description: typeof payload.description === 'string' ? payload.description : '',
    action: 'condition',
    condition: typeof payload.condition === 'string' ? payload.condition : '',
    condition_type: typeof payload.condition_type === 'string' ? payload.condition_type : '',
    field_id: typeof payload.field_id === 'string' ? payload.field_id : '',
    meta_key: typeof payload.meta_key === 'string' ? payload.meta_key : '',
    value_text: typeof payload.value_text === 'string' ? payload.value_text : '',
    type_text: typeof payload.type_text === 'string' ? payload.type_text : '',
    settings: isRecord(payload.settings) ? cloneSerializable(payload.settings) : {},
  };

  const normalize = definition?.parseData || definition?.normalizeData;
  const normalized = normalize ? normalize(baseData) : baseData;

  return ensureNodeDefaults({
    type: 'action',
    id: typeof payload.id === 'string' ? payload.id : createWorkflowNodeId('condition'),
    data: normalized,
    children: [],
    branches: createEmptyBranches(),
  });
}

export function createBranchCollection(branches?: Partial<WorkflowBranches> | null): WorkflowBranches {
  return {
    action_true: Array.isArray(branches?.action_true) ? branches!.action_true : [],
    action_false: Array.isArray(branches?.action_false) ? branches!.action_false : [],
  };
}

function isContainerNode(node: WorkflowNode): boolean {
  return isConditionAction(node.data) || !!node.branches;
}

function collectLocation(
  nodes: WorkflowNode[],
  targetId: string,
  parent: WorkflowNode | null,
  container: WorkflowNode[],
  containerKey: WorkflowContainerKey
): WorkflowNodeLocation | null {
  for (let index = 0; index < nodes.length; index += 1) {
    const node = nodes[index];

    if (node.id === targetId) {
      return {
        node,
        parent,
        index,
        container,
        containerKey,
        branchKey: containerKey === 'children' ? undefined : containerKey,
      };
    }

    if (Array.isArray(node.children) && node.children.length > 0) {
      const childLocation = collectLocation(node.children, targetId, node, node.children, 'children');
      if (childLocation) {
        return childLocation;
      }
    }

    if (isContainerNode(node)) {
      const branches = createBranchCollection(node.branches);
      const trueLocation = collectLocation(branches.action_true, targetId, node, branches.action_true, 'action_true');
      if (trueLocation) {
        return trueLocation;
      }

      const falseLocation = collectLocation(branches.action_false, targetId, node, branches.action_false, 'action_false');
      if (falseLocation) {
        return falseLocation;
      }
    }
  }

  return null;
}

export function findWorkflowNodeLocation(nodes: WorkflowNode[], targetId: string): WorkflowNodeLocation | null {
  return collectLocation(nodes, targetId, null, nodes, 'children');
}

export function findWorkflowNodeById(nodes: WorkflowNode[], targetId: string): WorkflowNode | null {
  const location = findWorkflowNodeLocation(nodes, targetId);
  return location ? location.node : null;
}

export function walkWorkflowNodes(
  nodes: WorkflowNode[],
  visitor: (node: WorkflowNode, location: WorkflowNodeLocation) => void
): void {
  const traverse = (
    list: WorkflowNode[],
    parent: WorkflowNode | null,
    container: WorkflowNode[],
    containerKey: WorkflowContainerKey
  ) => {
    list.forEach((node, index) => {
      const location: WorkflowNodeLocation = {
        node,
        parent,
        index,
        container,
        containerKey,
        branchKey: containerKey === 'children' ? undefined : containerKey,
      };

      visitor(node, location);

      if (Array.isArray(node.children) && node.children.length) {
        traverse(node.children, node, node.children, 'children');
      }

      if (isContainerNode(node)) {
        const branches = createBranchCollection(node.branches);
        traverse(branches.action_true, node, branches.action_true, 'action_true');
        traverse(branches.action_false, node, branches.action_false, 'action_false');
      }
    });
  };

  traverse(nodes, null, nodes, 'children');
}

export function cloneWorkflowNode(node: WorkflowNode): WorkflowNode {
  const clone = ensureNodeDefaults({
    type: node.type,
    id: createWorkflowNodeId(node.type),
    data: cloneSerializable(node.data),
    children: (node.children || []).map((child) => cloneWorkflowNode(child)),
    branches: node.branches
      ? {
          action_true: node.branches.action_true.map((child) => cloneWorkflowNode(child)),
          action_false: node.branches.action_false.map((child) => cloneWorkflowNode(child)),
        }
      : undefined,
    branchKey: node.branchKey,
    parentId: node.parentId,
  });

  return clone;
}

export function replaceWorkflowNodeData(
  nodes: WorkflowNode[],
  targetId: string,
  patch: Record<string, unknown>
): boolean {
  const location = findWorkflowNodeLocation(nodes, targetId);

  if (!location) {
    return false;
  }

  location.node.data = {
    ...location.node.data,
    ...cloneSerializable(patch),
  };

  return true;
}

export function removeWorkflowNode(nodes: WorkflowNode[], targetId: string): boolean {
  const location = findWorkflowNodeLocation(nodes, targetId);

  if (!location) {
    return false;
  }

  location.container.splice(location.index, 1);
  return true;
}

export function insertWorkflowNodeAfter(
  nodes: WorkflowNode[],
  targetId: string,
  nextNode: WorkflowNode
): WorkflowNode | null {
  const location = findWorkflowNodeLocation(nodes, targetId);

  if (!location) {
    nodes.push(nextNode);
    return nextNode;
  }

  location.container.splice(location.index + 1, 0, nextNode);
  return nextNode;
}

export function insertWorkflowNodeAtEnd(nodes: WorkflowNode[], nextNode: WorkflowNode): WorkflowNode {
  nodes.push(nextNode);
  return nextNode;
}

export function insertWorkflowNodeIntoConditionBranch(
  nodes: WorkflowNode[],
  targetId: string,
  branchKey: WorkflowBranchKey,
  nextNode: WorkflowNode,
  fallbackAfterId?: string
): WorkflowNode | null {
  const location = findWorkflowNodeLocation(nodes, targetId);

  if (!location) {
    return null;
  }

  const conditionNode = location.node.data.action === 'condition' ? location.node : location.parent;
  if (!conditionNode || conditionNode.data.action !== 'condition') {
    return null;
  }

  const branches = createBranchCollection(conditionNode.branches);
  const branch = branches[branchKey];

  if (fallbackAfterId) {
    const fallbackLocation = findWorkflowNodeLocation(branch, fallbackAfterId);
    if (fallbackLocation) {
      fallbackLocation.container.splice(fallbackLocation.index + 1, 0, nextNode);
      conditionNode.branches = branches;
      return nextNode;
    }
  }

  branch.push(nextNode);
  conditionNode.branches = branches;
  return nextNode;
}

export function duplicateWorkflowNode(node: WorkflowNode): WorkflowNode {
  return cloneWorkflowNode(node);
}

export function moveWorkflowNode(
  nodes: WorkflowNode[],
  targetId: string,
  direction: 'up' | 'down'
): boolean {
  const location = findWorkflowNodeLocation(nodes, targetId);

  if (!location) {
    return false;
  }

  const nextIndex = direction === 'up' ? location.index - 1 : location.index + 1;

  if (nextIndex < 0 || nextIndex >= location.container.length) {
    return false;
  }

  const [node] = location.container.splice(location.index, 1);
  location.container.splice(nextIndex, 0, node);
  return true;
}

export function ensureBranchesOnNode(node: WorkflowNode): WorkflowBranches {
  node.branches = createBranchCollection(node.branches);
  return node.branches;
}

export function getBranchCollection(node: WorkflowNode): WorkflowBranches {
  return createBranchCollection(node.branches);
}

export function isConditionNode(node: WorkflowNode | null | undefined): boolean {
  return !!node && node.type === 'action' && isConditionAction(node.data);
}

interface NodeHome {
  parent: string;
  branch: string;
}

function collectFlowNodes(
  nodes: WorkflowNode[],
  parentId: string,
  branchKey: string,
  pool: Map<string, WorkflowNode>,
  meta: Map<string, NodeHome>,
  order: string[]
): void {
  nodes.forEach((node) => {
    if (!isWorkflowNode(node)) {
      return;
    }

    const children = Array.isArray(node.children) ? node.children : [];
    const branches = node.branches;

    // shallow copy without the structural containers; the rebuild reattaches them
    pool.set(node.id, { ...node, children: [], branches: undefined, branchKey: undefined });
    meta.set(node.id, { parent: parentId, branch: branchKey });
    order.push(node.id);

    if (branches) {
      collectFlowNodes(Array.isArray(branches.action_true) ? branches.action_true : [], node.id, 'action_true', pool, meta, order);
      collectFlowNodes(Array.isArray(branches.action_false) ? branches.action_false : [], node.id, 'action_false', pool, meta, order);
    } else if (children.length) {
      // Linear children (rare in this model); keep them tied to this exact parent
      // so they can only ever graft back here.
      collectFlowNodes(children, node.id, 'children', pool, meta, order);
    }
  });
}

function getConnectionSource(node: WorkflowNode): { sourceId: string; sourceHandle: string } {
  const from = node.data?.connection_from;

  if (!isRecord(from)) {
    return { sourceId: '', sourceHandle: 'output' };
  }

  return {
    sourceId: String(from.source_id || ''),
    sourceHandle: String(from.source_handle || 'output'),
  };
}

interface ReconcileContext {
  pool: Map<string, WorkflowNode>;
  meta: Map<string, NodeHome>;
  order: string[];
  used: Set<string>;
}

function materializeNode(id: string, branchKey: WorkflowBranchKey | undefined, ctx: ReconcileContext): WorkflowNode {
  ctx.used.add(id);
  const node = ctx.pool.get(id) as WorkflowNode;

  if (branchKey) {
    node.branchKey = branchKey;
  } else {
    delete node.branchKey;
  }

  // conditions nest their branches; linear nodes continue via siblings
  if (isConditionAction(node.data)) {
    node.children = [];
    node.branches = {
      action_true: buildBranch(id, 'true', id, 'action_true', 'action_true', ctx),
      action_false: buildBranch(id, 'false', id, 'action_false', 'action_false', ctx),
    };
  } else {
    node.children = [];
    node.branches = undefined;
  }

  return node;
}

function buildBranch(
  wireSource: string,
  wireHandle: string,
  homeParent: string,
  homeBranch: string,
  branchKey: WorkflowBranchKey | undefined,
  ctx: ReconcileContext
): WorkflowNode[] {
  const result: WorkflowNode[] = [];

  // 1) connected chain: follow the wiring from the current output handle
  let currentSource = wireSource;
  let currentHandle = wireHandle;

  for (;;) {
    let nextId: string | null = null;

    for (const [id, node] of ctx.pool) {
      if (ctx.used.has(id)) {
        continue;
      }

      const { sourceId, sourceHandle } = getConnectionSource(node);

      if (sourceId !== '' && sourceId === currentSource && sourceHandle === currentHandle) {
        nextId = id;
        break;
      }
    }

    if (nextId === null) {
      break;
    }

    result.push(materializeNode(nextId, branchKey, ctx));
    currentSource = nextId;
    currentHandle = 'output';
  }

  // 2) graft un-wired nodes stored directly in this container, in order
  ctx.order.forEach((id) => {
    if (ctx.used.has(id)) {
      return;
    }

    const home = ctx.meta.get(id);

    if (home && home.parent === homeParent && home.branch === homeBranch) {
      result.push(materializeNode(id, branchKey, ctx));
    }
  });

  return result;
}

/**
 * Rebuild the workflow's execution order and branch nesting from each node's
 * stored wiring (`data.connection_from`), which is authoritative.
 *
 * The nested arrays that back the canvas can drift out of sync with the drawn
 * connections after edits (reordering, inserting a delay before a nested
 * condition, duplicating nodes), leaving a branch whose array order no longer
 * matches the flow. Persisting that drift makes the runtime walker execute nodes
 * in the wrong order (e.g. a message firing before the delay meant to precede it).
 *
 * Reconstruction is two-phase: follow the wiring to rebuild the connected
 * skeleton in execution order, then graft any un-wired node (typically a branch
 * leaf) back into the exact container it was stored in. Content with no wiring
 * rebuilds identically to what was stored (safe no-op); if any node cannot be
 * placed exactly once, the input is returned untouched.
 */
export function reconcileWorkflowContentFromConnections(nodes: WorkflowNode[]): WorkflowNode[] {
  if (!Array.isArray(nodes) || nodes.length === 0) {
    return nodes;
  }

  const triggers = nodes.filter((node) => node.type === 'trigger');

  if (triggers.length === 0) {
    return nodes;
  }

  const ctx: ReconcileContext = {
    pool: new Map<string, WorkflowNode>(),
    meta: new Map<string, NodeHome>(),
    order: [],
    used: new Set<string>(),
  };

  collectFlowNodes(nodes.filter((node) => node.type !== 'trigger'), '', '', ctx.pool, ctx.meta, ctx.order);

  const rebuilt: WorkflowNode[] = [];

  triggers.forEach((trigger) => {
    rebuilt.push(trigger);
    buildBranch(trigger.id, 'output', '', '', undefined, ctx).forEach((node) => rebuilt.push(node));
  });

  // every pooled node must be placed exactly once, else keep the stored structure
  if (ctx.used.size !== ctx.pool.size) {
    return nodes;
  }

  return rebuilt;
}

export function isStopNode(node: WorkflowNode | null | undefined): boolean {
  return !!node && node.type === 'action' && isStopAction(node.data);
}

export function isDelayNode(node: WorkflowNode | null | undefined): boolean {
  return !!node && node.type === 'action' && isDelayAction(node.data);
}

export function isSnippetNode(node: WorkflowNode | null | undefined): boolean {
  return !!node && node.type === 'action' && isSnippetAction(node.data);
}

export function isPlaceholderNode(node: WorkflowNode | null | undefined): boolean {
  return !!node && node.type === 'action' && isPlaceholderAction(node.data);
}

