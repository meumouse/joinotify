/**
 * triggerSettings.ts
 *
 * Helpers to decide whether a trigger node still has required settings to fill.
 * Triggers like "Order status changed" declare `requireSettings` and a settings
 * schema; until the required fields are filled the workflow cannot run reliably.
 *
 * @since 1.4.8
 */
import type { WorkflowFieldSchema, WorkflowNode, WorkflowRegistryItem } from '../types/workflowBuilder';

// Schema keys handled by the dedicated trigger fields (title/integration/trigger
// /description), never part of the per-trigger settings payload.
const RESERVED_TRIGGER_KEYS = new Set(['title', 'description', 'trigger', 'context']);

function isEmptyValue(value: unknown): boolean {
  if (value === null || value === undefined) {
    return true;
  }

  if (typeof value === 'string') {
    return value.trim() === '';
  }

  if (Array.isArray(value)) {
    return value.length === 0;
  }

  return false;
}

/**
 * Settings schema fields a trigger exposes (excluding the reserved base fields).
 */
export function getTriggerSettingsSchema(definition: WorkflowRegistryItem | undefined): WorkflowFieldSchema[] {
  if (!definition || !Array.isArray(definition.schema)) {
    return [];
  }

  return definition.schema.filter((field) => field && field.key && !RESERVED_TRIGGER_KEYS.has(field.key));
}

/**
 * Whether a trigger node still needs its required settings to be configured.
 */
export function triggerNeedsSetup(
  node: WorkflowNode | null | undefined,
  definition: WorkflowRegistryItem | undefined,
): boolean {
  if (!node || node.type !== 'trigger' || !definition?.requireSettings) {
    return false;
  }

  const settings = node.data?.settings && typeof node.data.settings === 'object'
    ? (node.data.settings as Record<string, unknown>)
    : {};

  const requiredFields = getTriggerSettingsSchema(definition).filter((field) => field.required);

  if (requiredFields.length) {
    return requiredFields.some((field) => isEmptyValue(settings[field.key]));
  }

  // No explicit required fields: mirror the backend "settings is empty" check.
  return Object.keys(settings).length === 0;
}
