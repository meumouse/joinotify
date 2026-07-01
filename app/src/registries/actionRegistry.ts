/**
 * actionRegistry.ts
 *
 * Legacy-compatibility bridge over the builder's action registry. Converts the
 * modern ActionDefinition shape into the legacy WorkflowRegistryItem shape and
 * re-exports catalog/lookup helpers so older builder code keeps working.
 *
 * @since 2.0.0
 */
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

/**
 * Converts a modern action definition into the legacy registry item shape.
 *
 * @since 2.0.0
 * @param {ActionDefinition} definition The modern action definition.
 * @returns {WorkflowRegistryItem} The legacy registry item.
 */
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

/**
 * Ensures the registry is initialized and returns the catalog in legacy shape.
 *
 * @since 2.0.0
 * @returns {WorkflowRegistryItem[]} The legacy action catalog.
 */
function ensureLegacyCatalog(): WorkflowRegistryItem[] {
  ensureActionRegistry();
  return getNewActionCatalog().map(toLegacyActionDefinition);
}

/**
 * Hydrates the registry from backend data and returns the legacy catalog.
 *
 * @since 2.0.0
 * @param {Array<Record<string, unknown>>} [rawActions] Backend action records.
 * @returns {WorkflowRegistryItem[]} The legacy action catalog.
 */
export function normalizeActionCatalog(rawActions: Array<Record<string, unknown>> = []): WorkflowRegistryItem[] {
  hydrateBuilderActionsFromBackend(rawActions);
  return ensureLegacyCatalog();
}

/**
 * Replaces the active action catalog with backend records.
 *
 * @since 2.0.0
 * @param {Array<Record<string, unknown>>} [rawActions] Backend action records.
 */
export function setActionCatalog(rawActions: Array<Record<string, unknown>> = []): void {
  setNewActionCatalog(rawActions);
}

/**
 * Returns the current action catalog in legacy shape.
 *
 * @since 2.0.0
 * @returns {WorkflowRegistryItem[]} The legacy action catalog.
 */
export function getActionCatalog(): WorkflowRegistryItem[] {
  return ensureLegacyCatalog();
}

/**
 * Returns a single action definition by ID in legacy shape.
 *
 * @since 2.0.0
 * @param {string} actionId The action ID.
 * @returns {WorkflowRegistryItem|undefined} The legacy item, or undefined.
 */
export function getActionDefinition(actionId: string): WorkflowRegistryItem | undefined {
  const definition = getNewActionDefinition(actionId);
  return definition ? toLegacyActionDefinition(definition) : undefined;
}

/**
 * Returns the actions available for a given context in legacy shape.
 *
 * @since 2.0.0
 * @param {string} context The context ID.
 * @returns {WorkflowRegistryItem[]} The matching legacy items.
 */
export function getActionsForContext(context: string): WorkflowRegistryItem[] {
  return getNewActionsForContext(context).map(toLegacyActionDefinition);
}

/**
 * Builds a preview string for an action node.
 *
 * @since 2.0.0
 * @param {WorkflowNode} node The action node.
 * @returns {string} The preview text.
 */
export function getActionRegistryPreview(node: WorkflowNode): string {
  return getNewActionRegistryPreview(node);
}

export const ACTION_REGISTRY: WorkflowRegistryItem[] = getActionCatalog();

export { registerBuilderAction };
