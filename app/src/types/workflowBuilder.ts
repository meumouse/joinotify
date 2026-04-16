export type WorkflowStatus = 'publish' | 'draft' | 'trash';

export type BuilderStep = 'start' | 'library' | 'import' | 'trigger' | 'canvas';

export type WorkflowNodeKind = 'trigger' | 'action';

export type WorkflowBranchKey = 'action_true' | 'action_false';

export type WorkflowContainerKey = 'children' | WorkflowBranchKey;

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

export interface WorkflowFieldOption {
  label: string;
  value: string | number | boolean;
  description?: string;
  disabled?: boolean;
  icon?: string;
}

export interface WorkflowFieldCondition {
  key: string;
  value: unknown;
  operator?: 'eq' | 'neq' | 'in' | 'not_in' | 'truthy' | 'falsy';
}

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

export interface WorkflowFieldValidationError {
  key: string;
  message: string;
}

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

export interface WorkflowBranches {
  action_true: WorkflowNode[];
  action_false: WorkflowNode[];
}

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

export interface WorkflowNodeLocation {
  node: WorkflowNode;
  parent: WorkflowNode | null;
  index: number;
  container: WorkflowNode[];
  containerKey: WorkflowContainerKey;
  branchKey?: WorkflowBranchKey;
}

export interface WorkflowPostMeta {
  type: 'joinotify-workflow';
  title: string;
  date: string;
  status: WorkflowStatus | string;
  modified: string;
  category: string;
  [key: string]: unknown;
}

export interface ExportedWorkflowFile {
  plugin_version: string;
  post: WorkflowPostMeta;
  workflow_content: WorkflowNode[];
  [key: string]: unknown;
}

export interface TriggerNodeData extends Record<string, unknown> {
  title: string;
  description: string;
  trigger: string;
  context: string;
  settings?: Record<string, unknown>;
}

export interface ActionNodeData extends Record<string, unknown> {
  title: string;
  description: string;
  action: string;
  message?: string;
  sender?: string;
  receiver?: string;
  settings?: Record<string, unknown>;
}

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
  condition_content?: Record<string, unknown>;
  settings?: Record<string, unknown>;
}

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

export interface PlaceholderNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  action?: 'dynamic_placeholder';
  dynamic_placeholder_text?: string;
  dynamic_placeholder_value?: string;
  settings?: Record<string, unknown>;
}

export interface SnippetNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  action?: 'snippet_php';
  snippet_php?: string;
  settings?: Record<string, unknown>;
}

export interface WorkflowPlaceholderItem {
  placeholder: string;
  description: string;
  category?: string;
  group?: string;
  triggers?: string[];
  replacement?: Record<string, unknown>;
  [key: string]: unknown;
}

export interface WorkflowPlaceholderGroup {
  id: string;
  label: string;
  description?: string;
  items: WorkflowPlaceholderItem[];
}

export interface WorkflowImportResult {
  ok: boolean;
  file?: ExportedWorkflowFile;
  errors: string[];
  warnings: string[];
}

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
  placeholders?: Record<string, unknown> | Array<Record<string, unknown>>;
  links?: Record<string, string>;
  permissions?: Record<string, unknown>;
  rest?: Record<string, string>;
  i18n?: Record<string, string>;
  debug_mode?: boolean;
  [key: string]: unknown;
}
