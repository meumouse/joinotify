import { getActionDefinition } from '../registries/actionRegistry';
import { getTriggerDefinition } from '../registries/triggerRegistry';
import {
  cloneSerializable,
  isConditionNode,
  isRecord,
  ensureBranchesOnNode,
  reconcileWorkflowContentFromConnections,
} from '../utils/workflowTree';
import { normalizeWorkflowFile } from '../parsers/workflowParser';
import { __, textDomain } from '../utils/i18n';
import type {
  ExportedWorkflowFile,
  WorkflowBranches,
  WorkflowNode,
  WorkflowPostMeta,
} from '../types/workflowBuilder';

const EDITOR_META_KEYS = [
  'connection_from',
  'connection_mode',
  'connection_break_before',
  'canvas_position',
] as const;

function pickEditorMeta(data: Record<string, unknown>): Record<string, unknown> {
  const meta: Record<string, unknown> = {};

  EDITOR_META_KEYS.forEach((key) => {
    if (Object.prototype.hasOwnProperty.call(data, key)) {
      meta[key] = cloneSerializable(data[key]);
    }
  });

  return meta;
}

function serializeNodeData(node: WorkflowNode): Record<string, unknown> {
  const data = isRecord(node.data) ? cloneSerializable(node.data) : {};
  const editorMeta = pickEditorMeta(data);

  if (node.type === 'trigger') {
    const context = String(data.context || '');
    const trigger = String(data.trigger || '');
    const definition = context && trigger ? getTriggerDefinition(context, trigger) : undefined;

    if (definition?.serializeData) {
      return {
        ...definition.serializeData(data),
        ...editorMeta,
      };
    }

    return {
      ...data,
      ...editorMeta,
    };
  }

  const action = String(data.action || '');
  const definition = action ? getActionDefinition(action) : undefined;

  if (definition?.serializeData) {
    return {
      ...definition.serializeData(data),
      ...editorMeta,
    };
  }

  return {
    ...data,
    ...editorMeta,
  };
}

function serializeLinearChildren(children: WorkflowNode[]): WorkflowNode[] {
  return (children || []).map((child) => serializeWorkflowNode(child));
}

function serializeBranchChildren(branches: WorkflowBranches): Record<string, WorkflowNode[]> {
  return {
    action_true: serializeLinearChildren(branches.action_true || []),
    action_false: serializeLinearChildren(branches.action_false || []),
  };
}

export function serializeWorkflowNode(node: WorkflowNode): Record<string, unknown> {
  const payload: Record<string, unknown> = {
    ...cloneSerializable(node),
    id: node.id,
    type: node.type,
    data: serializeNodeData(node),
  };

  delete payload.branchKey;
  delete payload.parentId;

  if (isConditionNode(node) || node.branches) {
    const branches = ensureBranchesOnNode(node);
    payload.children = serializeBranchChildren(branches);
  } else {
    payload.children = serializeLinearChildren(node.children || []);
  }

  return payload;
}

export function serializeWorkflowFile(file: ExportedWorkflowFile): ExportedWorkflowFile {
  const normalized = normalizeWorkflowFile(file);
  const post: WorkflowPostMeta = {
    ...cloneSerializable(normalized.post),
    type: 'joinotify-workflow',
    title: typeof normalized.post.title === 'string' ? normalized.post.title : __('My automation', textDomain),
    date: typeof normalized.post.date === 'string' ? normalized.post.date : '',
    status: typeof normalized.post.status === 'string' ? normalized.post.status : 'draft',
    modified: typeof normalized.post.modified === 'string' ? normalized.post.modified : '',
    category: typeof normalized.post.category === 'string' ? normalized.post.category : '',
  };

  // Realign the stored tree with the authoritative wiring before serializing, so
  // a branch whose array order drifted from the drawn connections is persisted in
  // the real execution order (idempotent; no-op for already well-formed content).
  const reconciled = reconcileWorkflowContentFromConnections(normalized.workflow_content);

  return {
    ...cloneSerializable(normalized),
    plugin_version: typeof normalized.plugin_version === 'string' && normalized.plugin_version.trim()
      ? normalized.plugin_version
      : '1.0.0',
    post,
    workflow_content: reconciled.map((node) => serializeWorkflowNode(node)) as WorkflowNode[],
  };
}

export function serializeWorkflowToJson(file: ExportedWorkflowFile): string {
  return JSON.stringify(serializeWorkflowFile(file), null, 2);
}

