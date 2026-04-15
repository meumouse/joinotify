import type { Component } from 'vue';
import type { WorkflowFieldSchema } from '../../../types/workflowBuilder';

export type BuilderActionSlug = string;
export type BuilderActionContext = string;
export type BuilderActionBranchKey = string;
export type BuilderActionValidationMap = Record<string, string>;

export interface WorkflowActionItem {
  id: string;
  type: 'action';
  data: Record<string, any> & {
    action: string;
    title?: string;
    description?: string;
    sender?: string;
    receiver?: string;
  };
  children?: Record<string, WorkflowActionItem[]>;
}

export interface ActionDefinition {
  action: BuilderActionSlug;
  title: string;
  description: string;
  icon?: string;
  externalIcon?: boolean;
  context?: BuilderActionContext[];
  hasSettings: boolean;
  priority: number;
  isExpansible?: boolean;
  defaultData?: Record<string, unknown>;
  cardComponent?: Component;
  settingsComponent?: Component;
  settingsSchema?: WorkflowFieldSchema[];
  branchKeys?: BuilderActionBranchKey[];
  branchLabels?: Record<string, string>;
  normalizeData?: (data: Record<string, unknown>) => Record<string, unknown>;
  serializeData?: (data: Record<string, unknown>) => Record<string, unknown>;
  buildDescription?: (data: Record<string, unknown>) => string;
  validate?: (data: Record<string, unknown>) => BuilderActionValidationMap;
  enabled?: boolean;
  tags?: string[];
}

export interface BackendActionDefinition extends Partial<ActionDefinition> {
  action?: string;
  slug?: string;
  id?: string;
  title?: string;
  label?: string;
  description?: string;
  icon?: string;
  external_icon?: boolean;
  externalIcon?: boolean;
  context?: string | string[];
  contexts?: string | string[];
  has_settings?: boolean;
  hasSettings?: boolean;
  priority?: number | string;
  is_expansible?: boolean;
  isExpansible?: boolean;
  default_data?: Record<string, unknown>;
  defaultData?: Record<string, unknown>;
  settings_schema?: WorkflowFieldSchema[];
  settingsSchema?: WorkflowFieldSchema[];
  settings_component?: Component;
  settingsComponent?: Component;
  card_component?: Component;
  cardComponent?: Component;
  branch_keys?: BuilderActionBranchKey[];
  branchKeys?: BuilderActionBranchKey[];
  branch_labels?: Record<string, string>;
  branchLabels?: Record<string, string>;
  enabled?: boolean;
  tags?: string[];
}
