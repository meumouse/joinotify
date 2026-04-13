import { getActionDefinition } from '../registries/actionRegistry';
import { TRIGGER_CONTEXTS } from '../registries/triggerContexts';
import { getTriggerDefinition } from '../registries/triggerRegistry';
import { __, textDomain } from '../utils/i18n';
import { createWorkflowNodeId } from '../utils/workflowIds';
import type { ExportedWorkflowFile, WorkflowImportResult, WorkflowNode, WorkflowPostMeta } from '../types/workflowBuilder';

function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

function cloneSerializable<T>(value: T): T {
  return JSON.parse(JSON.stringify(value ?? null));
}

function nowString(): string {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function normalizePostMeta(value: unknown, fallbackCategory = ''): WorkflowPostMeta {
  const source = isRecord(value) ? value : {};
  const extra = cloneSerializable(source);

  delete extra.content;
  delete extra.workflow_content;
  delete extra.workflow;
  delete extra.workflow_file;
  delete extra.plugin_version;
  delete extra.post;
  delete extra.post_id;
  delete extra.selected_node_id;
  delete extra.created_at;
  delete extra.updated_at;
  delete extra.edit_url;
  delete extra.export_url;
  delete extra.loading;
  delete extra.is_new;

  const title = typeof source.title === 'string' && source.title.trim() ? source.title : 'My automation';
  const status = typeof source.status === 'string' && source.status ? source.status : 'draft';
  const category = typeof source.category === 'string' && source.category ? source.category : fallbackCategory;

  return {
    ...extra,
    type: 'joinotify-workflow',
    title,
    date: typeof source.date === 'string' && source.date ? source.date : nowString(),
    status,
    modified: typeof source.modified === 'string' && source.modified ? source.modified : nowString(),
    category,
  };
}

function normalizeNodeData(node: WorkflowNode): Record<string, unknown> {
  const data = isRecord(node.data) ? cloneSerializable(node.data) : {};

  if (node.type === 'trigger') {
    const context = typeof data.context === 'string' ? data.context : '';
    const trigger = typeof data.trigger === 'string' ? data.trigger : '';
    const def = getTriggerDefinition(context, trigger);

    return def?.parseData ? def.parseData(data) : data;
  }

  if (node.type === 'action') {
    const action = typeof data.action === 'string' ? data.action : '';
    const def = getActionDefinition(action);

    return def?.parseData ? def.parseData(data) : data;
  }

  return data;
}

function normalizeNode(node: unknown, fallbackType = 'action'): WorkflowNode {
  const source = isRecord(node) ? node : {};
  const type = typeof source.type === 'string' && source.type ? source.type : fallbackType;
  const rawChildren = Array.isArray(source.children) ? source.children : [];

  return {
    ...cloneSerializable(source),
    id: typeof source.id === 'string' && source.id ? source.id : createWorkflowNodeId(type),
    type,
    data: normalizeNodeData({
      id: typeof source.id === 'string' ? source.id : '',
      type,
      data: isRecord(source.data) ? cloneSerializable(source.data) : {},
      children: [],
    }),
    children: rawChildren.map((child) => normalizeNode(child)),
  };
}

function detectCategory(workflowContent: WorkflowNode[]): string {
  const trigger = workflowContent.find((node) => node.type === 'trigger');
  const context = trigger && isRecord(trigger.data) && typeof trigger.data.context === 'string' ? trigger.data.context : '';

  return context || TRIGGER_CONTEXTS[0]?.id || '';
}

function normalizeExportedFile(input: unknown): ExportedWorkflowFile {
  const source = isRecord(input) ? (input as Record<string, any>) : {};
  const exported = isRecord(source.workflow_file) ? source.workflow_file : source;
  const legacyWorkflow = isRecord(source.workflow) ? source.workflow : Array.isArray(source.content) ? source : {};
  const content = Array.isArray(exported.workflow_content)
    ? exported.workflow_content
    : Array.isArray(source.workflow_content)
      ? source.workflow_content
      : Array.isArray(source.content)
        ? source.content
        : Array.isArray(legacyWorkflow.content)
          ? legacyWorkflow.content
          : [];
  const workflowContent = content.map((node) => normalizeNode(node));
  const post = normalizePostMeta(
    isRecord(exported.post) ? exported.post : isRecord(source.post) ? source.post : legacyWorkflow.post || legacyWorkflow,
    detectCategory(workflowContent)
  );

  if (!post.category) {
    post.category = detectCategory(workflowContent);
  }

  return {
    ...(() => {
      const extra = cloneSerializable(exported);
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
    })(),
    plugin_version: typeof exported.plugin_version === 'string' && exported.plugin_version ? exported.plugin_version : '1.0.0',
    post,
    workflow_content: workflowContent,
  };
}

export function parseWorkflowFile(input: unknown): WorkflowImportResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (!isRecord(input)) {
    return { ok: false, errors: [__('Invalid file.', textDomain)], warnings };
  }

  const root = input as Record<string, any>;
  const candidate = root.workflow_file ? root.workflow_file : root;
  const legacyWorkflow = isRecord(root.workflow) ? root.workflow : Array.isArray(root.content) ? root : null;
  const hasPost = isRecord(candidate.post);
  const hasContent =
    Array.isArray(candidate.workflow_content) ||
    Array.isArray(candidate.content) ||
    Array.isArray(legacyWorkflow?.content);
  const hasVersion = typeof candidate.plugin_version === 'string' || typeof root.plugin_version === 'string';

  if (!hasVersion) {
    warnings.push(__('plugin_version missing; version normalized automatically.', textDomain));
  }

  if (!hasPost && !legacyWorkflow) {
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

export function parseWorkflowFromJson(json: string): WorkflowImportResult {
  try {
    const parsed = JSON.parse(json);
    return parseWorkflowFile(parsed);
  } catch (error) {
    return { ok: false, errors: [__('Invalid JSON.', textDomain)], warnings: [] };
  }
}

export function createWorkflowFileFromParts(payload: Partial<ExportedWorkflowFile> & { title?: string; category?: string; status?: string } = {}): ExportedWorkflowFile {
  const post = normalizePostMeta(
    {
      type: 'joinotify-workflow',
      title: payload.title || payload.post?.title || 'My automation',
      date: payload.post?.date || nowString(),
      status: payload.status || payload.post?.status || 'draft',
      modified: payload.post?.modified || nowString(),
      category: payload.category || payload.post?.category || detectCategory(payload.workflow_content || []),
    },
    payload.category || payload.post?.category || ''
  );

  return normalizeExportedFile({
    plugin_version: payload.plugin_version || '1.0.0',
    post,
    workflow_content: Array.isArray(payload.workflow_content) ? payload.workflow_content : [],
  });
}

export function normalizeWorkflowFile(file: ExportedWorkflowFile): ExportedWorkflowFile {
  return normalizeExportedFile(file);
}
