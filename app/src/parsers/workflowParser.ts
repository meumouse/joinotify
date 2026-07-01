/**
 * workflowParser.ts
 *
 * Parses and normalizes imported/stored workflow files into the in-memory node
 * tree used by the builder. Handles legacy formats, node data normalization via
 * the trigger/action registries, condition branch reconstruction, and editor
 * metadata preservation. Exposes helpers to validate and build workflow files.
 *
 * @since 2.0.0
 */
import { getActionDefinition } from '../registries/actionRegistry';
import { TRIGGER_CONTEXTS, getTriggerContextById } from '../registries/triggerContexts';
import { getTriggerDefinition } from '../registries/triggerRegistry';
import { __, textDomain } from '../utils/i18n';
import { createWorkflowNodeId } from '../utils/workflowIds';
import {
  cloneSerializable,
  createActionNode,
  createBranchCollection,
  createConditionNode,
  createEmptyBranches,
  createTriggerNode,
  ensureBranchesOnNode,
  ensureNodeDefaults,
  isConditionAction,
  isRecord,
  isWorkflowNode,
  normalizeBranchKey,
} from '../utils/workflowTree';
import type {
  BuilderBootstrap,
  ExportedWorkflowFile,
  WorkflowBranchKey,
  WorkflowBranches,
  WorkflowImportResult,
  WorkflowNode,
  WorkflowPostMeta,
} from '../types/workflowBuilder';

/**
 * Returns the current date/time as a `YYYY-MM-DD HH:mm:ss` string.
 *
 * @since 2.0.0
 * @returns {string} The formatted timestamp.
 */
function nowString(): string {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

/**
 * Clones the source and removes internal/editor-only root fields.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} source The raw root object.
 * @returns {Record<string, unknown>} A clone without internal root fields.
 */
function stripInternalRootFields(source: Record<string, unknown>) {
  const extra = cloneSerializable(source);

  delete extra.workflow_content;
  delete extra.workflow;
  delete extra.content;
  delete extra.post;
  delete extra.plugin_version;
  delete extra.post_id;
  delete extra.selected_node_id;
  delete extra.created_at;
  delete extra.updated_at;
  delete extra.edit_url;
  delete extra.export_url;
  delete extra.loading;
  delete extra.is_new;

  return extra;
}

/**
 * Normalizes post metadata, filling defaults for title, status, date, and category.
 *
 * @since 2.0.0
 * @param {unknown} value The raw post metadata.
 * @param {string} [fallbackCategory] Category used when none is present.
 * @returns {WorkflowPostMeta} The normalized post metadata.
 */
function normalizePostMeta(value: unknown, fallbackCategory = ''): WorkflowPostMeta {
  const source = isRecord(value) ? value : {};
  const extra = stripInternalRootFields(source);
  const title = typeof source.title === 'string' && source.title.trim() ? source.title.trim() : __('My automation', textDomain);
  const status = typeof source.status === 'string' && source.status.trim() ? source.status.trim() : 'draft';
  const category = typeof source.category === 'string' && source.category.trim() ? source.category.trim() : fallbackCategory;

  return {
    ...extra,
    type: 'joinotify-workflow',
    title,
    date: typeof source.date === 'string' && source.date.trim() ? source.date : nowString(),
    status,
    modified: typeof source.modified === 'string' && source.modified.trim() ? source.modified : nowString(),
    category,
  };
}

/**
 * Normalizes a node's data payload using the matching registry definition.
 *
 * @since 2.0.0
 * @param {unknown} rawData The raw node data.
 * @param {'trigger'|'action'} type The node kind.
 * @param {string} nodeId The node ID used when building base data.
 * @returns {Record<string, unknown>} The normalized node data.
 */
function normalizeNodeData(
  rawData: unknown,
  type: 'trigger' | 'action',
  nodeId: string
): Record<string, unknown> {
  const data = isRecord(rawData) ? cloneSerializable(rawData) : {};
  const editorMeta = pickEditorMeta(data);

  if (type === 'trigger') {
    const context = typeof data.context === 'string' ? data.context : '';
    const trigger = typeof data.trigger === 'string' ? data.trigger : '';
    const definition = context && trigger ? getTriggerDefinition(context, trigger) : undefined;
    const base = createTriggerNode({
      id: nodeId,
      title: typeof data.title === 'string' ? data.title : '',
      description: typeof data.description === 'string' ? data.description : '',
      trigger,
      context,
      settings: isRecord(data.settings) ? data.settings : {},
    });

    if (definition?.serializeData) {
      return {
        ...definition.serializeData(base.data),
        ...editorMeta,
      };
    }

    return {
      ...base.data,
      ...data,
      ...editorMeta,
      title: typeof data.title === 'string' && data.title.trim() ? data.title : base.data.title,
      description: typeof data.description === 'string' ? data.description : base.data.description,
      trigger: trigger || base.data.trigger,
      context: context || base.data.context,
    };
  }

  const action = typeof data.action === 'string' ? data.action : '';
  const definition = action ? getActionDefinition(action) : undefined;

  if (action === 'condition') {
    const base = createConditionNode({
      ...data,
      id: nodeId,
      title: typeof data.title === 'string' ? data.title : '',
      description: typeof data.description === 'string' ? data.description : '',
      condition: typeof data.condition === 'string' ? data.condition : '',
      condition_type: typeof data.condition_type === 'string' ? data.condition_type : '',
      field_id: typeof data.field_id === 'string' ? data.field_id : '',
      meta_key: typeof data.meta_key === 'string' ? data.meta_key : '',
      value_text: typeof data.value_text === 'string' ? data.value_text : '',
      type_text: typeof data.type_text === 'string' ? data.type_text : '',
      settings: isRecord(data.settings) ? data.settings : {},
    }, definition);

    return definition?.serializeData
      ? {
          ...definition.serializeData(base.data),
          ...editorMeta,
        }
      : {
          ...base.data,
          ...editorMeta,
        };
  }

  const base = createActionNode(action, {
    id: nodeId,
    title: typeof data.title === 'string' ? data.title : '',
    description: typeof data.description === 'string' ? data.description : '',
    message: typeof data.message === 'string' ? data.message : '',
    sender: typeof data.sender === 'string' ? data.sender : '',
    receiver: typeof data.receiver === 'string' ? data.receiver : '',
    settings: isRecord(data.settings) ? data.settings : {},
    ...data,
  }, definition);

  if (definition?.serializeData) {
    return {
      ...definition.serializeData(base.data),
      ...editorMeta,
    };
  }

  return {
    ...base.data,
    ...data,
    ...editorMeta,
    title: typeof data.title === 'string' && data.title.trim() ? data.title : base.data.title,
    description: typeof data.description === 'string' ? data.description : base.data.description,
    action,
  };
}

/**
 * Coerces a value to a finite number, or null when not possible.
 *
 * @since 2.0.0
 * @param {unknown} value The value to coerce.
 * @returns {number|null} The finite number, or null.
 */
function toFiniteNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }

  return null;
}

/**
 * Extracts editor-only metadata (connection wiring, canvas position) from node data.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} data The node data.
 * @returns {Record<string, unknown>} The extracted editor metadata.
 */
function pickEditorMeta(data: Record<string, unknown>): Record<string, unknown> {
  const meta: Record<string, unknown> = {};

  if (isRecord(data.connection_from)) {
    meta.connection_from = {
      source_id: String(data.connection_from.source_id || ''),
      source_handle: String(data.connection_from.source_handle || 'output'),
      target_handle: String(data.connection_from.target_handle || 'input'),
    };
  }

  if (Object.prototype.hasOwnProperty.call(data, 'connection_mode')) {
    meta.connection_mode = data.connection_mode == null ? null : String(data.connection_mode);
  }

  if (Object.prototype.hasOwnProperty.call(data, 'connection_break_before')) {
    meta.connection_break_before = data.connection_break_before;
  }

  if (isRecord(data.canvas_position)) {
    const x = toFiniteNumber(data.canvas_position.x);
    const y = toFiniteNumber(data.canvas_position.y);

    if (x !== null && y !== null) {
      meta.canvas_position = { x, y };
    }
  }

  return meta;
}

/**
 * Normalizes a linear list of child nodes.
 *
 * @since 2.0.0
 * @param {unknown} children The raw children value.
 * @returns {WorkflowNode[]} The normalized child nodes.
 */
function normalizeLinearChildren(children: unknown): WorkflowNode[] {
  if (!Array.isArray(children)) {
    return [];
  }

  return children
    .map((child) => normalizeWorkflowNode(child))
    .filter(Boolean) as WorkflowNode[];
}

/**
 * Normalizes a list of nodes belonging to a specific condition branch.
 *
 * @since 2.0.0
 * @param {unknown[]} nodes The raw branch nodes.
 * @param {WorkflowBranchKey} branchKey The branch key to assign.
 * @returns {WorkflowNode[]} The normalized branch nodes.
 */
function normalizeBranchNodes(nodes: unknown[], branchKey: WorkflowBranchKey): WorkflowNode[] {
  if (!Array.isArray(nodes)) {
    return [];
  }

  return nodes
    .map((child) => normalizeWorkflowNode(child, branchKey))
    .filter(Boolean) as WorkflowNode[];
}

/**
 * Reconstructs a condition node's true/false branches from various stored shapes.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} source The raw condition node object.
 * @param {string} fallbackNodeId The node ID used as a fallback.
 * @returns {WorkflowBranches} The normalized branches.
 */
function normalizeConditionBranches(source: Record<string, unknown>, fallbackNodeId: string): WorkflowBranches {
  const rawBranches = isRecord(source.branches) ? source.branches : null;
  const rawChildren = rawBranches || source.children;
  const branches = createEmptyBranches();

  if (isRecord(rawChildren)) {
    branches.action_true = normalizeBranchNodes(Array.isArray(rawChildren.action_true) ? rawChildren.action_true : [], 'action_true');
    branches.action_false = normalizeBranchNodes(Array.isArray(rawChildren.action_false) ? rawChildren.action_false : [], 'action_false');
    return branches;
  }

  if (Array.isArray(rawChildren)) {
    const bucketed: Record<WorkflowBranchKey, unknown[]> = {
      action_true: [],
      action_false: [],
    };

    let hasBranchHints = false;

    for (const item of rawChildren) {
      const branchKey = isRecord(item) ? normalizeBranchKey(item.branchKey || item.branch || item.condition_branch) : undefined;

      if (branchKey) {
        bucketed[branchKey].push(item);
        hasBranchHints = true;
        continue;
      }

      bucketed.action_true.push(item);
    }

    if (hasBranchHints) {
      branches.action_true = normalizeBranchNodes(bucketed.action_true, 'action_true');
      branches.action_false = normalizeBranchNodes(bucketed.action_false, 'action_false');
    } else {
      branches.action_true = normalizeBranchNodes(bucketed.action_true, 'action_true');
    }
  }

  return branches;
}

/**
 * Determines whether a value is a legacy connector node that should be dropped.
 *
 * @since 2.0.0
 * @param {unknown} value The value to test.
 * @returns {boolean} True when the value is a legacy connector node.
 */
function isLegacyConnectorNode(value: unknown): boolean {
  return isRecord(value) && typeof value.type === 'string' && value.type.startsWith('connector');
}

/**
 * Normalizes a single raw node into a WorkflowNode, or null when invalid.
 *
 * @since 2.0.0
 * @param {unknown} input The raw node.
 * @param {WorkflowBranchKey} [branchKey] Branch key when the node lives in a branch.
 * @returns {WorkflowNode|null} The normalized node, or null.
 */
export function normalizeWorkflowNode(input: unknown, branchKey?: WorkflowBranchKey): WorkflowNode | null {
  if (!isRecord(input) || isLegacyConnectorNode(input)) {
    return null;
  }

  const rawType = String(input.type || '').trim().toLowerCase();
  const inferredType: 'trigger' | 'action' = rawType === 'trigger'
    ? 'trigger'
    : 'action';
  const id = typeof input.id === 'string' && input.id.trim() ? input.id : createWorkflowNodeId(inferredType);
  const data = normalizeNodeData(input.data, inferredType, id);

  const node: WorkflowNode = ensureNodeDefaults({
    type: inferredType,
    id,
    data,
    children: [],
  });

  if (branchKey) {
    node.branchKey = branchKey;
  }

  if (inferredType === 'action' && isConditionAction(node.data)) {
    node.branches = normalizeConditionBranches(input, id);
    node.children = [];
    return node;
  }

  node.children = normalizeLinearChildren(input.children);

  return node;
}

/**
 * Normalizes a list (or wrapping object) into an array of WorkflowNodes.
 *
 * @since 2.0.0
 * @param {unknown} value The raw list or wrapper.
 * @returns {WorkflowNode[]} The normalized node list.
 */
function normalizeWorkflowNodesList(value: unknown): WorkflowNode[] {
  if (Array.isArray(value)) {
    return value.map((item) => normalizeWorkflowNode(item)).filter(Boolean) as WorkflowNode[];
  }

  if (isRecord(value) && Array.isArray(value.workflow_content)) {
    return normalizeWorkflowNodesList(value.workflow_content);
  }

  return [];
}

/**
 * Detects the workflow category from its first trigger's context.
 *
 * @since 2.0.0
 * @param {WorkflowNode[]} workflowContent The workflow node tree.
 * @returns {string} The detected category, or the first available context.
 */
function detectCategory(workflowContent: WorkflowNode[]): string {
  for (const node of workflowContent) {
    if (node.type !== 'trigger') {
      continue;
    }

    const context = typeof node.data.context === 'string' ? node.data.context : '';
    if (context) {
      return context;
    }
  }

  return TRIGGER_CONTEXTS[0]?.id || '';
}

/**
 * Normalizes a raw exported file (including legacy shapes) into ExportedWorkflowFile.
 *
 * @since 2.0.0
 * @param {unknown} input The raw file object.
 * @returns {ExportedWorkflowFile} The normalized exported file.
 */
function normalizeExportedFile(input: unknown): ExportedWorkflowFile {
  const source = isRecord(input) ? input : {};
  const candidate = isRecord(source.workflow_file) ? source.workflow_file : source;
  const legacyWorkflow = isRecord(source.workflow) ? source.workflow : isRecord(source.content) ? source : {};
  const content = Array.isArray(candidate.workflow_content)
    ? candidate.workflow_content
    : Array.isArray(source.workflow_content)
      ? source.workflow_content
      : Array.isArray(source.content)
        ? source.content
        : Array.isArray(legacyWorkflow.content)
          ? legacyWorkflow.content
          : [];
  const workflowContent = normalizeWorkflowNodesList(content);
  const postSource = isRecord(candidate.post) ? candidate.post : isRecord(source.post) ? source.post : legacyWorkflow.post || legacyWorkflow;
  const post = normalizePostMeta(postSource, detectCategory(workflowContent));

  if (!post.category) {
    post.category = detectCategory(workflowContent);
  }

  return {
    ...stripInternalRootFields(candidate),
    plugin_version: typeof candidate.plugin_version === 'string' && candidate.plugin_version.trim()
      ? candidate.plugin_version
      : typeof source.plugin_version === 'string' && source.plugin_version.trim()
        ? source.plugin_version
        : '1.0.0',
    post,
    workflow_content: workflowContent,
  };
}

/**
 * Validates and parses a raw workflow file object into an import result.
 *
 * @since 2.0.0
 * @param {unknown} input The raw file object.
 * @returns {WorkflowImportResult} The parse result with errors/warnings.
 */
export function parseWorkflowFile(input: unknown): WorkflowImportResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (!isRecord(input)) {
    return { ok: false, errors: [__('Invalid file.', textDomain)], warnings };
  }

  const root = input as Record<string, unknown>;
  const candidate = isRecord(root.workflow_file) ? root.workflow_file : root;
  const legacyWorkflow = isRecord(root.workflow) ? root.workflow : root;
  const hasPost = isRecord(candidate.post) || isRecord(root.post) || isRecord(legacyWorkflow.post);
  const hasContent =
    Array.isArray(candidate.workflow_content) ||
    Array.isArray(root.workflow_content) ||
    Array.isArray(root.content) ||
    Array.isArray(legacyWorkflow.content);
  const hasVersion = typeof candidate.plugin_version === 'string' || typeof root.plugin_version === 'string';

  if (!hasVersion) {
    warnings.push(__('plugin_version missing; version normalized automatically.', textDomain));
  }

  if (!hasPost) {
    errors.push(__('Missing post field.', textDomain));
  }

  if (!hasContent) {
    errors.push(__('Missing workflow_content field.', textDomain));
  }

  if (errors.length > 0) {
    return { ok: false, errors, warnings };
  }

  const file = normalizeExportedFile(candidate);
  return { ok: true, file, errors, warnings };
}

/**
 * Parses a JSON string into a workflow import result.
 *
 * @since 2.0.0
 * @param {string} json The JSON source.
 * @returns {WorkflowImportResult} The parse result.
 */
export function parseWorkflowFromJson(json: string): WorkflowImportResult {
  try {
    const parsed = JSON.parse(json);
    return parseWorkflowFile(parsed);
  } catch {
    return { ok: false, errors: [__('Invalid JSON.', textDomain)], warnings: [] };
  }
}

/**
 * Builds a normalized ExportedWorkflowFile from partial parts.
 *
 * @since 2.0.0
 * @param {Object} [payload] Partial file data plus optional title/category/status.
 * @returns {ExportedWorkflowFile} The assembled, normalized file.
 */
export function createWorkflowFileFromParts(
  payload: Partial<ExportedWorkflowFile> & { title?: string; category?: string; status?: string } = {}
): ExportedWorkflowFile {
  const workflowContent = Array.isArray(payload.workflow_content) ? normalizeWorkflowNodesList(payload.workflow_content) : [];
  const post = normalizePostMeta(
    payload.post || {
      type: 'joinotify-workflow',
      title: payload.title || __('My automation', textDomain),
      date: nowString(),
      status: payload.status || 'draft',
      modified: nowString(),
      category: payload.category || detectCategory(workflowContent),
    },
    payload.category || (payload.post && typeof payload.post.category === 'string' ? payload.post.category : '') || detectCategory(workflowContent)
  );

  return normalizeExportedFile({
    ...stripInternalRootFields(payload as Record<string, unknown>),
    plugin_version: payload.plugin_version || '1.0.0',
    post,
    workflow_content: workflowContent,
  });
}

/**
 * Re-normalizes an existing exported workflow file.
 *
 * @since 2.0.0
 * @param {ExportedWorkflowFile} file The file to normalize.
 * @returns {ExportedWorkflowFile} The normalized file.
 */
export function normalizeWorkflowFile(file: ExportedWorkflowFile): ExportedWorkflowFile {
  return normalizeExportedFile(file);
}

/**
 * Creates a new workflow node from a registry definition.
 *
 * @since 2.0.0
 * @param {'trigger'|'action'} nodeType The node kind to create.
 * @param {Record<string, unknown>} [payload] Initial node data.
 * @returns {WorkflowNode} The created node.
 */
export function createWorkflowNodeFromRegistry(
  nodeType: 'trigger' | 'action',
  payload: Record<string, unknown> = {}
): WorkflowNode {
  if (nodeType === 'trigger') {
    return createTriggerNode(payload);
  }

  const action = String(payload.action || '');

  if (action === 'condition') {
    return createConditionNode(payload, getActionDefinition(action));
  }

  return createActionNode(action, payload, getActionDefinition(action));
}
