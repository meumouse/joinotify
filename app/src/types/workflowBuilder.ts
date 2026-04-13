export interface WorkflowPostMeta {
  type: 'joinotify-workflow';
  title: string;
  date: string;
  status: string;
  modified: string;
  category: string;
  [key: string]: unknown;
}

export interface WorkflowNode {
  id: string;
  type: string;
  data: Record<string, unknown>;
  children: WorkflowNode[];
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
}

export interface ActionNodeData extends Record<string, unknown> {
  title: string;
  description: string;
  action: string;
  message?: string;
  sender?: string;
  receiver?: string;
}

export interface ConditionNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  condition?: string;
}

export interface DelayNodeData extends Record<string, unknown> {
  title?: string;
  description?: string;
  delay_type?: string;
  delay_period?: string;
  date_value?: string;
  time_value?: string;
}

export type BuilderStep = 'start' | 'library' | 'import' | 'trigger' | 'canvas';

export interface WorkflowImportResult {
  ok: boolean;
  file?: ExportedWorkflowFile;
  errors: string[];
  warnings: string[];
}

export interface WorkflowFieldSchema {
  key: string;
  label: string;
  component: 'input' | 'textarea' | 'select' | 'switch';
  placeholder?: string;
  options?: Array<{ label: string; value: string }>;
  description?: string;
}

export interface WorkflowRegistryItem {
  id: string;
  label: string;
  description: string;
  icon: string;
  context: string;
  schema: WorkflowFieldSchema[];
  settingsComponent?: string;
  parseData?: (data: Record<string, unknown>) => Record<string, unknown>;
  serializeData?: (data: Record<string, unknown>) => Record<string, unknown>;
  preview?: (data: Record<string, unknown>) => string;
  validate?: (data: Record<string, unknown>) => string[];
}

export interface WorkflowContextDefinition {
  id: string;
  label: string;
  description: string;
  icon: string;
  category: string;
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
  placeholders?: Record<string, unknown>;
  links?: Record<string, string>;
  permissions?: Record<string, unknown>;
  rest?: Record<string, string>;
  i18n?: Record<string, string>;
}
