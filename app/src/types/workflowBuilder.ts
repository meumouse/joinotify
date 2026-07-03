/**
 * workflowBuilder.ts
 *
 * Core TypeScript types for the workflow builder: node kinds and branches,
 * field schemas, registry item shapes, trigger/action node data, placeholders,
 * import/export file structures, and the builder bootstrap payload.
 *
 * @since 2.0.0
 */

/** Publication status of a workflow. */
export type WorkflowStatus = 'publish' | 'draft' | 'trash';

/** The active step of the builder wizard. */
export type BuilderStep = 'start' | 'library' | 'import' | 'trigger' | 'canvas';

/** Whether a node is a trigger or an action. */
export type WorkflowNodeKind = 'trigger' | 'action';

/** The branch key for a condition node's true/false path. */
export type WorkflowBranchKey = 'action_true' | 'action_false';

/** The key of a container that holds child nodes. */
export type WorkflowContainerKey = 'children' | WorkflowBranchKey;

/** Supported field editor component types. */
export type WorkflowFieldComponent =
  | 'input'
  | 'textarea'
  | 'select'
  | 'switch'
  | 'group'
  | 'repeater'
  | 'number'
  | 'date'
  | 'time'
  | 'code'
  | 'placeholder'
  | 'custom';

/** A selectable option for a field. */
export interface WorkflowFieldOption {
  label: string;
  value: string | number | boolean;
  description?: string;
  disabled?: boolean;
  icon?: string;
}

/** A visibility condition evaluated against other field values. */
export interface WorkflowFieldCondition {
  key: string;
  value: unknown;
  operator?: 'eq' | 'neq' | 'in' | 'not_in' | 'truthy' | 'falsy';
}

/** Declarative schema describing a single settings field. */
export interface WorkflowFieldSchema {
  key: string;
  label: string;
  component: WorkflowFieldComponent;
  placeholder?: string;
  description?: string;
  helper?: string;
  required?: boolean;
  rows?: number;
  defaultValue?: unknown;
  options?: WorkflowFieldOption[];
  fields?: WorkflowFieldSchema[];
  repeatable?: boolean;
  minItems?: number;
  maxItems?: number;
  condition?: WorkflowFieldCondition[];
  componentProps?: Record<string, unknown>;
}

/** A validation error tied to a specific field key. */
export interface WorkflowFieldValidationError {
  key: string;
  message: string;
}

/** A registered trigger or action definition and its behavior hooks. */
export interface WorkflowRegistryItem {
  id: string;
  label: string;
  description: string;
  icon: string;
  iconSvg?: string;
  context?: string | string[];
  contexts?: string[];
  category?: string;
  schema: WorkflowFieldSchema[];
  settingsComponent?: string;
  defaultData?: Record<string, unknown>;
  normalizeData?: (data: Record<string, unknown>) => Record<string, unknown>;
  parseData?: (data: Record<string, unknown>) => Record<string, unknown>;
  serializeData?: (data: Record<string, unknown>) => Record<string, unknown>;
  preview?: (data: Record<string, unknown>) => string;
  validate?: (data: Record<string, unknown>) => string[];
  requireSettings?: boolean;
  enabled?: boolean;
  branchKeys?: string[];
  branchLabels?: Record<string, string>;
  hasSettings?: boolean;
  isExpansible?: boolean;
}

/** A trigger context (integration/source) definition. */
export interface WorkflowContextDefinition {
  id: string;
  label: string;
  description: string;
  icon: string;
  icon_svg?: string;
  icon_url?: string;
  category: string;
  enabled?: boolean;
}

/** The true/false child branches of a condition node. */
export interface WorkflowBranches {
  action_true: WorkflowNode[];
  action_false: WorkflowNode[];
}

/** A single node in the workflow tree. */
export interface WorkflowNode {
  id: string;
  type: WorkflowNodeKind;
  data: Record<string, unknown>;
  children: WorkflowNode[];
  branches?: WorkflowBranches;
  branchKey?: WorkflowBranchKey;
  parentId?: string;
  [key: string]: unknown;
}

/** The resolved location of a node within its parent container. */
export interface WorkflowNodeLocation {
  node: WorkflowNode;
  parent: WorkflowNode | null;
  index: number;
  container: WorkflowNode[];
  containerKey: WorkflowContainerKey;
  branchKey?: WorkflowBranchKey;
}

/** Post metadata attached to an exported workflow. */
export interface WorkflowPostMeta {
  type: 'joinotify-workflow';
  title: string;
  date: string;
  status: WorkflowStatus | string;
  modified: string;
  category: string;
  [key: string]: unknown;
}

/**
 * A visual-only sticky note (label) placed on the builder canvas.
 *
 * Notes document the flow in markdown and never affect execution: they live in
 * a top-level `editor_notes` array, fully decoupled from `workflow_content`.
 */
export interface WorkflowEditorNote {
  id: string;
  /** Markdown source text rendered on the canvas. */
  content: string;
  /** Hex color for the note background/border. */
  color: string;
  position: { x: number; y: number };
  width: number;
  height: number;
}

/** The structure of an exported/importable workflow file. */
export interface ExportedWorkflowFile {
  plugin_version: string;
  post: WorkflowPostMeta;
  workflow_content: WorkflowNode[];
  /** Visual-only canvas annotations; ignored by the execution engine. */
  editor_notes?: WorkflowEditorNote[];
  [key: string]: unknown;
}

/** Data payload for a trigger node. */
export interface TriggerNodeData extends Record<string, unknown> {
  title: string;
  description: string;
  trigger: string;
  context: string;
  settings?: Record<string, unknown>;
}

/** Data payload for a message/action node. */
export interface ActionNodeData extends Record<string, unknown> {
  title: string;
  description: string;
  action: string;
  message?: string;
  sender?: string;
  receiver?: string;
  settings?: Record<string, unknown>;
}

/** Data payload for a condition node. */
export interface ConditionNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  action?: 'condition';
  condition?: string;
  condition_type?: string;
  field_id?: string;
  meta_key?: string;
  value_text?: string;
  type_text?: string;
  products?: Array<Record<string, unknown>>;
  condition_content?: Record<string, unknown>;
  settings?: Record<string, unknown>;
}

/** Data payload for a time-delay node. */
export interface DelayNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  action?: 'time_delay';
  delay_type?: string;
  delay_value?: string | number;
  delay_period?: string;
  date_value?: string;
  time_value?: string;
  delay_timestamp?: string | number;
  settings?: Record<string, unknown>;
}

/** Data payload for a dynamic-placeholder node. */
export interface PlaceholderNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  action?: 'dynamic_placeholder';
  dynamic_placeholder_text?: string;
  dynamic_placeholder_value?: string;
  settings?: Record<string, unknown>;
}

/** Data payload for a PHP-snippet node. */
export interface SnippetNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  action?: 'snippet_php';
  snippet_php?: string;
  settings?: Record<string, unknown>;
}

/** A single dynamic placeholder available for messages. */
export interface WorkflowPlaceholderItem {
  placeholder: string;
  description: string;
  category?: string;
  group?: string;
  triggers?: string[];
  replacement?: Record<string, unknown>;
  [key: string]: unknown;
}

/** A named group of related placeholders. */
export interface WorkflowPlaceholderGroup {
  id: string;
  label: string;
  description?: string;
  items: WorkflowPlaceholderItem[];
}

/** The outcome of parsing an imported workflow file. */
export interface WorkflowImportResult {
  ok: boolean;
  file?: ExportedWorkflowFile;
  errors: string[];
  warnings: string[];
}

/** The bootstrap payload injected into the builder page. */
export interface BuilderBootstrap {
  version?: string;
  page?: string;
  title?: string;
  workflow?: unknown;
  workflow_file?: unknown;
  start_templates?: Record<string, unknown>;
  templates?: Array<Record<string, unknown>>;
  actions?: Array<Record<string, unknown>>;
  triggers?: Record<string, Array<Record<string, unknown>>>;
  trigger_contexts?: Array<Record<string, unknown>>;
  trigger_availability?: Record<string, unknown>;
  placeholders?: Record<string, unknown> | Array<Record<string, unknown>>;
  links?: Record<string, string>;
  permissions?: Record<string, unknown>;
  rest?: Record<string, string>;
  i18n?: Record<string, string>;
  debug_mode?: boolean;
  [key: string]: unknown;
}
