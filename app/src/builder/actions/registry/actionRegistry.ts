/**
 * actionRegistry.ts
 *
 * Central registry of builder action definitions. Bootstraps the core actions,
 * registers/merges frontend and backend definitions, normalizes their shape,
 * and exposes lookup helpers (definition, defaults, settings component, branch
 * keys, description) plus catalog/context queries and a reactive revision
 * counter used to trigger recomputation in consuming components.
 *
 * @since 2.0.0
 */
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
import { resolveSvgMarkup } from '../../../utils/icon';
import { __, textDomain } from '../../../utils/i18n';

const registry = new Map<BuilderActionSlug, ActionDefinition>();
const bootstrapped = ref(false);
const bootstrapping = ref(false);
const registryRevision = ref(0);

/**
 * Increments the registry revision counter to signal reactive consumers.
 *
 * @since 2.0.0
 * @returns {void}
 */
function bumpRegistryRevision(): void {
  registryRevision.value += 1;
}

/**
 * Coerces a raw context value into a trimmed, non-empty string array.
 *
 * @since 2.0.0
 * @param {unknown} value Raw context (string or array).
 * @returns {string[]} Normalized list of context slugs.
 */
function cleanContext(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((entry) => String(entry).trim()).filter(Boolean);
  }

  if (typeof value === 'string' && value.trim()) {
    return [value.trim()];
  }

  return [];
}

/**
 * Parses a priority value into a finite number, falling back when invalid.
 *
 * @since 2.0.0
 * @param {unknown} value Raw priority value.
 * @param {number} [fallback=0] Value returned when parsing fails.
 * @returns {number} The parsed priority.
 */
function cleanPriority(value: unknown, fallback = 0): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

/**
 * Produces a shallow clone of a definition with its array/object fields copied,
 * so stored definitions are not mutated by callers.
 *
 * @since 2.0.0
 * @param {ActionDefinition} definition Definition to clone.
 * @returns {ActionDefinition} A defensive copy of the definition.
 */
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

/**
 * Merges a new definition over an existing one, preferring the new values but
 * preserving current fields (context, components, callbacks) when the new
 * definition omits them.
 *
 * @since 2.0.0
 * @param {ActionDefinition|undefined} current Existing definition, if any.
 * @param {ActionDefinition} next Incoming definition to merge.
 * @returns {ActionDefinition} The merged, cloned definition.
 */
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

/**
 * Registers the core actions once, guarding against re-entrant bootstrapping.
 *
 * @since 2.0.0
 * @returns {void}
 */
function ensureBootstrapped(): void {
  if (bootstrapped.value || bootstrapping.value) {
    return;
  }

  bootstrapping.value = true;
  registerCoreActions();
  bootstrapping.value = false;
  bootstrapped.value = true;
}

/**
 * Normalizes a frontend or backend definition into the canonical
 * ActionDefinition shape, reconciling camelCase and snake_case keys and
 * resolving the icon markup.
 *
 * @since 2.0.0
 * @param {ActionDefinition|BackendActionDefinition} definition Raw definition.
 * @returns {ActionDefinition|null} The normalized definition, or null when it lacks an action slug.
 */
function normalizeDefinition(definition: ActionDefinition | BackendActionDefinition): ActionDefinition | null {
  const action = String(definition.action || definition.slug || definition.id || '').trim();

  if (!action) {
    return null;
  }

  const iconValue = String(definition.icon || '').trim();
  const iconSvgValue = resolveSvgMarkup(definition.iconSvg, iconValue);

  return cloneDefinition({
    action,
    title: String(definition.title || definition.label || action),
    description: String(definition.description || ''),
    icon: iconSvgValue ? '' : iconValue,
    iconSvg: iconSvgValue,
    externalIcon: Boolean(definition.externalIcon ?? definition.external_icon),
    context: cleanContext(definition.context || definition.contexts),
    category: String(definition.category || '').trim() || 'general',
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

/**
 * Builds a placeholder definition for an unknown action, using any provided
 * metadata and sensible defaults so the workflow can still render/save.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @param {Partial<ActionDefinition>} [metadata={}] Optional partial metadata.
 * @returns {ActionDefinition} The fallback definition.
 */
function createFallbackDefinition(action: string, metadata: Partial<ActionDefinition> = {}): ActionDefinition {
  return cloneDefinition({
    action,
    title: metadata.title || action,
    description: metadata.description || __('Configuration component not available for this action.', textDomain),
    icon: metadata.icon || 'sparkles',
    iconSvg: metadata.iconSvg || '',
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

/**
 * Registers (or merges into an existing) a single action definition and bumps
 * the registry revision.
 *
 * @since 2.0.0
 * @param {ActionDefinition|BackendActionDefinition} definition Definition to register.
 * @returns {ActionDefinition|null} A clone of the stored definition, or null when invalid.
 */
export function registerBuilderAction(definition: ActionDefinition | BackendActionDefinition): ActionDefinition | null {
  const normalized = normalizeDefinition(definition);

  if (!normalized) {
    return null;
  }

  const current = registry.get(normalized.action);
  const merged = mergeDefinition(current, normalized);
  registry.set(merged.action, merged);
  bumpRegistryRevision();
  return cloneDefinition(merged);
}

/**
 * Registers many action definitions, ignoring any that fail to normalize.
 *
 * @since 2.0.0
 * @param {Array<ActionDefinition|BackendActionDefinition>} definitions Definitions to register.
 * @returns {ActionDefinition[]} The successfully registered definitions.
 */
export function registerBuilderActions(definitions: Array<ActionDefinition | BackendActionDefinition>): ActionDefinition[] {
  return definitions.map((definition) => registerBuilderAction(definition)).filter(Boolean) as ActionDefinition[];
}

/**
 * Registers action definitions received from the backend, falling back to the
 * current catalog when the input is empty or invalid.
 *
 * @since 2.0.0
 * @param {Array<Record<string, unknown>>} definitions Backend definitions.
 * @returns {ActionDefinition[]} The hydrated definitions, or the existing catalog.
 */
export function hydrateBuilderActionsFromBackend(definitions: Array<Record<string, unknown>>): ActionDefinition[] {
  if (!Array.isArray(definitions)) {
    return getBuilderActions();
  }

  const hydrated = definitions.map((definition) => registerBuilderAction(definition as BackendActionDefinition)).filter(Boolean) as ActionDefinition[];
  return hydrated.length ? hydrated : getBuilderActions();
}

/**
 * Looks up a single action definition by slug (bootstrapping if needed).
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {ActionDefinition|null} A clone of the definition, or null when absent.
 */
export function getBuilderAction(action: string): ActionDefinition | null {
  ensureBootstrapped();
  const definition = registry.get(String(action || '').trim());

  return definition ? cloneDefinition(definition) : null;
}

/**
 * Returns all enabled action definitions, sorted by descending priority and
 * then by title.
 *
 * @since 2.0.0
 * @returns {ActionDefinition[]} The sorted list of enabled definitions.
 */
export function getBuilderActions(): ActionDefinition[] {
  ensureBootstrapped();

  return Array.from(registry.values())
    .map((definition) => cloneDefinition(definition))
    .filter((definition) => definition.enabled !== false)
    .sort((left, right) => right.priority - left.priority || left.title.localeCompare(right.title));
}

/**
 * Returns enabled definitions available in the given context (context-less
 * actions are always included).
 *
 * @since 2.0.0
 * @param {BuilderActionContext} context Context slug to filter by.
 * @returns {ActionDefinition[]} Definitions available in that context.
 */
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

/**
 * Returns a copy of the default data for an action, or a minimal object with
 * just the action slug when none is registered.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {Record<string, unknown>} Default data for the action.
 */
export function getBuilderActionDefaults(action: string): Record<string, unknown> {
  const definition = getBuilderAction(action);
  return definition?.defaultData ? { ...definition.defaultData } : { action };
}

/**
 * Builds a truncated, human-readable description for an action from its data,
 * using the definition's builder when available or a fallback otherwise.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @param {Record<string, unknown>} data Action data payload.
 * @returns {string} The generated description.
 */
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

/**
 * Returns the settings component registered for an action, if any.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {Component|undefined} The settings component.
 */
export function getBuilderActionSettingsComponent(action: string): Component | undefined {
  return getBuilderAction(action)?.settingsComponent;
}

/**
 * Returns the card component registered for an action, if any.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {Component|undefined} The card component.
 */
export function getBuilderActionCardComponent(action: string): Component | undefined {
  return getBuilderAction(action)?.cardComponent;
}

/**
 * Indicates whether an action exposes configurable settings.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {boolean} True when the action has settings.
 */
export function hasBuilderActionSettings(action: string): boolean {
  return Boolean(getBuilderAction(action)?.hasSettings);
}

/**
 * Returns the branch keys declared by an action (empty when none).
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {string[]} The action's branch keys.
 */
export function getBuilderActionBranchKeys(action: string): string[] {
  return getBuilderAction(action)?.branchKeys || [];
}

/**
 * Resolves an action definition, returning a fallback definition when the
 * action is not registered.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @param {Partial<ActionDefinition>} [metadata={}] Optional metadata for the fallback.
 * @returns {ActionDefinition} The resolved or fallback definition.
 */
export function resolveBuilderAction(action: string, metadata: Partial<ActionDefinition> = {}): ActionDefinition {
  const definition = getBuilderAction(action);

  if (definition) {
    return definition;
  }

  return createFallbackDefinition(action, metadata);
}

/**
 * Creates a workflow action item for an action, normalizing its data and
 * assigning a generated id when none is provided.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @param {Record<string, unknown>} [data={}] Initial action data.
 * @returns {WorkflowActionItem} The constructed workflow action item.
 */
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

/**
 * Bootstraps the registry and hydrates it from a raw backend catalog.
 *
 * @since 2.0.0
 * @param {Array<Record<string, unknown>>} [rawActions=[]] Raw backend action list.
 * @returns {ActionDefinition[]} The resulting catalog.
 */
export function setActionCatalog(rawActions: Array<Record<string, unknown>> = []): ActionDefinition[] {
  ensureBootstrapped();
  return hydrateBuilderActionsFromBackend(rawActions);
}

/**
 * Returns the full enabled action catalog.
 *
 * @since 2.0.0
 * @returns {ActionDefinition[]} The action catalog.
 */
export function getActionCatalog(): ActionDefinition[] {
  return getBuilderActions();
}

/**
 * Alias for getBuilderAction: returns a single action definition by slug.
 *
 * @since 2.0.0
 * @param {string} action Action slug.
 * @returns {ActionDefinition|null} The definition, or null when absent.
 */
export function getActionDefinition(action: string): ActionDefinition | null {
  return getBuilderAction(action);
}

/**
 * Alias for getBuilderActionsByContext: lists actions for a context.
 *
 * @since 2.0.0
 * @param {BuilderActionContext} context Context slug.
 * @returns {ActionDefinition[]} Definitions available in that context.
 */
export function getActionsForContext(context: BuilderActionContext): ActionDefinition[] {
  return getBuilderActionsByContext(context);
}

/**
 * Builds the description preview for a workflow node from its action data.
 *
 * @since 2.0.0
 * @param {{ data?: Record<string, unknown> }} node Workflow node with optional data.
 * @returns {string} The preview description.
 */
export function getActionRegistryPreview(node: { data?: Record<string, unknown> }): string {
  const action = String(node?.data?.action || '');
  return buildActionDescription(action, node?.data || {});
}

/**
 * Ensures the action registry has been bootstrapped.
 *
 * @since 2.0.0
 * @returns {void}
 */
export function ensureActionRegistry(): void {
  ensureBootstrapped();
}

/**
 * Returns the current registry revision counter (bootstrapping if needed).
 *
 * @since 2.0.0
 * @returns {number} The current revision value.
 */
export function getActionRegistryRevision(): number {
  ensureBootstrapped();
  return registryRevision.value;
}
