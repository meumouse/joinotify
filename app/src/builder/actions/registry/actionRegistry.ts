import type { Component } from 'vue';
import { ref } from 'vue';
import { describeFallbackAction, truncateDescription } from '../utils/actionDescription';
import type {
  ActionDefinition,
  BackendActionDefinition,
  BuilderActionContext,
  BuilderActionSlug,
  WorkflowActionItem,
} from './types';
import { registerCoreActions } from './registerCoreActions';

const registry = new Map<BuilderActionSlug, ActionDefinition>();
const bootstrapped = ref(false);
const bootstrapping = ref(false);

function cleanContext(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((entry) => String(entry).trim()).filter(Boolean);
  }

  if (typeof value === 'string' && value.trim()) {
    return [value.trim()];
  }

  return [];
}

function cleanPriority(value: unknown, fallback = 0): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function cloneDefinition(definition: ActionDefinition): ActionDefinition {
  return {
    ...definition,
    context: Array.isArray(definition.context) ? [...definition.context] : [],
    defaultData: definition.defaultData ? { ...definition.defaultData } : {},
    branchKeys: Array.isArray(definition.branchKeys) ? [...definition.branchKeys] : undefined,
    branchLabels: definition.branchLabels ? { ...definition.branchLabels } : undefined,
    settingsSchema: Array.isArray(definition.settingsSchema) ? [...definition.settingsSchema] : undefined,
    tags: Array.isArray(definition.tags) ? [...definition.tags] : undefined,
  };
}

function mergeDefinition(current: ActionDefinition | undefined, next: ActionDefinition): ActionDefinition {
  return cloneDefinition({
    ...current,
    ...next,
    context: next.context && next.context.length > 0 ? next.context : current?.context || [],
    defaultData: {
      ...(current?.defaultData || {}),
      ...(next.defaultData || {}),
    },
    branchKeys: next.branchKeys && next.branchKeys.length > 0 ? next.branchKeys : current?.branchKeys,
    branchLabels: {
      ...(current?.branchLabels || {}),
      ...(next.branchLabels || {}),
    },
    settingsSchema: next.settingsSchema && next.settingsSchema.length > 0 ? next.settingsSchema : current?.settingsSchema,
    tags: next.tags && next.tags.length > 0 ? next.tags : current?.tags,
    settingsComponent: next.settingsComponent || current?.settingsComponent,
    cardComponent: next.cardComponent || current?.cardComponent,
    normalizeData: next.normalizeData || current?.normalizeData,
    serializeData: next.serializeData || current?.serializeData,
    buildDescription: next.buildDescription || current?.buildDescription,
    validate: next.validate || current?.validate,
    enabled: next.enabled ?? current?.enabled ?? true,
  });
}

function ensureBootstrapped(): void {
  if (bootstrapped.value || bootstrapping.value) {
    return;
  }

  bootstrapping.value = true;
  registerCoreActions();
  bootstrapping.value = false;
  bootstrapped.value = true;
}

function normalizeDefinition(definition: ActionDefinition | BackendActionDefinition): ActionDefinition | null {
  const action = String(definition.action || definition.slug || definition.id || '').trim();

  if (!action) {
    return null;
  }

  return cloneDefinition({
    action,
    title: String(definition.title || definition.label || action),
    description: String(definition.description || ''),
    icon: definition.icon || '',
    externalIcon: Boolean(definition.externalIcon ?? definition.external_icon),
    context: cleanContext(definition.context || definition.contexts),
    hasSettings: Boolean(definition.hasSettings ?? definition.has_settings ?? false),
    priority: cleanPriority(definition.priority, 0),
    isExpansible: Boolean(definition.isExpansible ?? definition.is_expansible),
    defaultData: {
      ...(definition.defaultData || definition.default_data || {}),
    },
    cardComponent: definition.cardComponent || definition.card_component,
    settingsComponent: definition.settingsComponent || definition.settings_component,
    settingsSchema: definition.settingsSchema || definition.settings_schema,
    branchKeys: definition.branchKeys || definition.branch_keys,
    branchLabels: definition.branchLabels || definition.branch_labels,
    normalizeData: definition.normalizeData,
    serializeData: definition.serializeData,
    buildDescription: definition.buildDescription,
    validate: definition.validate,
    enabled: definition.enabled ?? true,
    tags: Array.isArray(definition.tags) ? [...definition.tags] : [],
  });
}

function createFallbackDefinition(action: string, metadata: Partial<ActionDefinition> = {}): ActionDefinition {
  return cloneDefinition({
    action,
    title: metadata.title || action,
    description: metadata.description || 'Configuration component not available for this action.',
    icon: metadata.icon || 'sparkles',
    externalIcon: Boolean(metadata.externalIcon),
    context: metadata.context || [],
    hasSettings: Boolean(metadata.hasSettings ?? false),
    priority: Number(metadata.priority || 0),
    isExpansible: Boolean(metadata.isExpansible),
    defaultData: metadata.defaultData || { action },
    cardComponent: metadata.cardComponent,
    settingsComponent: metadata.settingsComponent,
    settingsSchema: metadata.settingsSchema,
    branchKeys: metadata.branchKeys,
    branchLabels: metadata.branchLabels,
    normalizeData: metadata.normalizeData,
    serializeData: metadata.serializeData,
    buildDescription: metadata.buildDescription,
    validate: metadata.validate,
    enabled: metadata.enabled ?? true,
    tags: metadata.tags || [],
  });
}

export function registerBuilderAction(definition: ActionDefinition | BackendActionDefinition): ActionDefinition | null {
  const normalized = normalizeDefinition(definition);

  if (!normalized) {
    return null;
  }

  const current = registry.get(normalized.action);
  const merged = mergeDefinition(current, normalized);
  registry.set(merged.action, merged);
  return cloneDefinition(merged);
}

export function registerBuilderActions(definitions: Array<ActionDefinition | BackendActionDefinition>): ActionDefinition[] {
  return definitions.map((definition) => registerBuilderAction(definition)).filter(Boolean) as ActionDefinition[];
}

export function hydrateBuilderActionsFromBackend(definitions: Array<Record<string, unknown>>): ActionDefinition[] {
  if (!Array.isArray(definitions)) {
    return getBuilderActions();
  }

  const hydrated = definitions.map((definition) => registerBuilderAction(definition as BackendActionDefinition)).filter(Boolean) as ActionDefinition[];
  return hydrated.length ? hydrated : getBuilderActions();
}

export function getBuilderAction(action: string): ActionDefinition | null {
  ensureBootstrapped();
  const definition = registry.get(String(action || '').trim());

  return definition ? cloneDefinition(definition) : null;
}

export function getBuilderActions(): ActionDefinition[] {
  ensureBootstrapped();

  return Array.from(registry.values())
    .map((definition) => cloneDefinition(definition))
    .filter((definition) => definition.enabled !== false)
    .sort((left, right) => right.priority - left.priority || left.title.localeCompare(right.title));
}

export function getBuilderActionsByContext(context: BuilderActionContext): ActionDefinition[] {
  const safeContext = String(context || '').trim();

  return getBuilderActions().filter((definition) => {
    const contexts = Array.isArray(definition.context) ? definition.context.map((value) => String(value).trim()).filter(Boolean) : [];

    if (!safeContext) {
      return true;
    }

    return contexts.length === 0 || contexts.includes(safeContext);
  });
}

export function getBuilderActionDefaults(action: string): Record<string, unknown> {
  const definition = getBuilderAction(action);
  return definition?.defaultData ? { ...definition.defaultData } : { action };
}

export function buildActionDescription(action: string, data: Record<string, unknown>): string {
  const definition = getBuilderAction(action);

  if (definition?.buildDescription) {
    return truncateDescription(definition.buildDescription(data || {}));
  }

  if (!definition) {
    return truncateDescription(describeFallbackAction(action, '', data || {}));
  }

  return truncateDescription(describeFallbackAction(definition.title, definition.description, data || {}));
}

export function getBuilderActionSettingsComponent(action: string): Component | undefined {
  return getBuilderAction(action)?.settingsComponent;
}

export function getBuilderActionCardComponent(action: string): Component | undefined {
  return getBuilderAction(action)?.cardComponent;
}

export function hasBuilderActionSettings(action: string): boolean {
  return Boolean(getBuilderAction(action)?.hasSettings);
}

export function getBuilderActionBranchKeys(action: string): string[] {
  return getBuilderAction(action)?.branchKeys || [];
}

export function resolveBuilderAction(action: string, metadata: Partial<ActionDefinition> = {}): ActionDefinition {
  const definition = getBuilderAction(action);

  if (definition) {
    return definition;
  }

  return createFallbackDefinition(action, metadata);
}

export function toWorkflowActionItem(action: string, data: Record<string, unknown> = {}): WorkflowActionItem {
  const definition = resolveBuilderAction(action);
  const nextData = definition.normalizeData ? definition.normalizeData({ ...definition.defaultData, ...data, action }) : { ...definition.defaultData, ...data, action };

  return {
    id: String(data.id || `${action}-${Math.random().toString(36).slice(2, 10)}`),
    type: 'action',
    data: nextData,
    children: {},
  };
}

export function setActionCatalog(rawActions: Array<Record<string, unknown>> = []): ActionDefinition[] {
  ensureBootstrapped();
  return hydrateBuilderActionsFromBackend(rawActions);
}

export function getActionCatalog(): ActionDefinition[] {
  return getBuilderActions();
}

export function getActionDefinition(action: string): ActionDefinition | null {
  return getBuilderAction(action);
}

export function getActionsForContext(context: BuilderActionContext): ActionDefinition[] {
  return getBuilderActionsByContext(context);
}

export function getActionRegistryPreview(node: { data?: Record<string, unknown> }): string {
  const action = String(node?.data?.action || '');
  return buildActionDescription(action, node?.data || {});
}

export function ensureActionRegistry(): void {
  ensureBootstrapped();
}
