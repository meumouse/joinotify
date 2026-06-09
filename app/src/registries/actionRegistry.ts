import type { Component } from 'vue';
import {
  buildActionDescription,
  ensureActionRegistry,
  getActionCatalog as getNewActionCatalog,
  getActionDefinition as getNewActionDefinition,
  getActionRegistryPreview as getNewActionRegistryPreview,
  getActionsForContext as getNewActionsForContext,
  hydrateBuilderActionsFromBackend,
  registerBuilderAction,
  setActionCatalog as setNewActionCatalog,
} from '../builder/actions/registry/actionRegistry';
import type { ActionDefinition } from '../builder/actions/registry/types';
import type { WorkflowNode, WorkflowRegistryItem } from '../types/workflowBuilder';

function toLegacyActionDefinition(definition: ActionDefinition): WorkflowRegistryItem {
  return {
    id: definition.action,
    label: definition.title,
    description: definition.description,
    icon: definition.icon || definition.title || definition.action,
    iconSvg: definition.iconSvg || '',
    context: Array.isArray(definition.context) ? [...definition.context] : [],
    contexts: Array.isArray(definition.context) ? [...definition.context] : [],
    category: definition.category || '',
    schema: definition.settingsSchema || [],
    settingsComponent: definition.settingsComponent ? (definition.settingsComponent as unknown as string) : '',
    defaultData: definition.defaultData || {},
    normalizeData: definition.normalizeData || definition.serializeData,
    parseData: definition.normalizeData || definition.serializeData,
    serializeData: definition.serializeData || definition.normalizeData,
    preview: definition.buildDescription
      ? (data: Record<string, unknown>) => definition.buildDescription?.(data || {}) || ''
      : (data: Record<string, unknown>) => buildActionDescription(definition.action, data || {}),
    validate: definition.validate
      ? (data: Record<string, unknown>) => Object.values(definition.validate?.(data || {}) || {})
      : undefined,
    requireSettings: Boolean(definition.hasSettings),
    enabled: definition.enabled,
    branchKeys: definition.branchKeys,
    branchLabels: definition.branchLabels,
    hasSettings: definition.hasSettings,
    isExpansible: definition.isExpansible,
  };
}

function ensureLegacyCatalog(): WorkflowRegistryItem[] {
  ensureActionRegistry();
  return getNewActionCatalog().map(toLegacyActionDefinition);
}

export function normalizeActionCatalog(rawActions: Array<Record<string, unknown>> = []): WorkflowRegistryItem[] {
  hydrateBuilderActionsFromBackend(rawActions);
  return ensureLegacyCatalog();
}

export function setActionCatalog(rawActions: Array<Record<string, unknown>> = []): void {
  setNewActionCatalog(rawActions);
}

export function getActionCatalog(): WorkflowRegistryItem[] {
  return ensureLegacyCatalog();
}

export function getActionDefinition(actionId: string): WorkflowRegistryItem | undefined {
  const definition = getNewActionDefinition(actionId);
  return definition ? toLegacyActionDefinition(definition) : undefined;
}

export function getActionsForContext(context: string): WorkflowRegistryItem[] {
  return getNewActionsForContext(context).map(toLegacyActionDefinition);
}

export function getActionRegistryPreview(node: WorkflowNode): string {
  return getNewActionRegistryPreview(node);
}

export const ACTION_REGISTRY: WorkflowRegistryItem[] = getActionCatalog();

export { registerBuilderAction };
