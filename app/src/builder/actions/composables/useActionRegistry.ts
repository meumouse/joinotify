/**
 * useActionRegistry.ts
 *
 * Vue composable that exposes the builder action registry to components.
 * Ensures the registry is bootstrapped, provides reactive access to the
 * action catalog and its revision, and wraps the registry lookup helpers
 * (definition, defaults, settings component, branch keys, description) plus
 * registration/hydration utilities.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import {
  buildActionDescription,
  ensureActionRegistry,
  getActionCatalog,
  getBuilderAction,
  getBuilderActionBranchKeys,
  getBuilderActionDefaults,
  getBuilderActionSettingsComponent,
  getBuilderActionsByContext,
  getActionsForContext,
  hydrateBuilderActionsFromBackend,
  getActionRegistryRevision,
  registerBuilderAction,
  registerBuilderActions,
  setActionCatalog,
} from '../registry/actionRegistry';
import type { ActionDefinition } from '../registry/types';

/**
 * Provides reactive access to the builder action registry and its helpers.
 *
 * @since 2.0.0
 * @returns {Object} Registry accessors: reactive actions/revision and lookup/registration helpers.
 */
export function useActionRegistry() {
  ensureActionRegistry();

  const actions = computed<ActionDefinition[]>(() => getActionCatalog());
  const revision = computed(() => getActionRegistryRevision());

  /**
   * Lists the action definitions available for the given builder context.
   *
   * @since 2.0.0
   * @param {string} context Builder context slug.
   * @returns {ActionDefinition[]} Actions matching the context.
   */
  function byContext(context: string) {
    return getActionsForContext(context);
  }

  /**
   * Retrieves a single action definition by its slug.
   *
   * @since 2.0.0
   * @param {string} action Action slug.
   * @returns {ActionDefinition|null} The definition, or null when not registered.
   */
  function get(action: string) {
    return getBuilderAction(action);
  }

  /**
   * Returns the default data payload for an action.
   *
   * @since 2.0.0
   * @param {string} action Action slug.
   * @returns {Record<string, unknown>} Default data for the action.
   */
  function defaults(action: string) {
    return getBuilderActionDefaults(action);
  }

  /**
   * Returns the registered settings component for an action.
   *
   * @since 2.0.0
   * @param {string} action Action slug.
   * @returns {Component|undefined} The settings component, if any.
   */
  function settingsComponent(action: string) {
    return getBuilderActionSettingsComponent(action);
  }

  /**
   * Returns the branch keys declared by an action (for expansible actions).
   *
   * @since 2.0.0
   * @param {string} action Action slug.
   * @returns {string[]} Branch keys for the action.
   */
  function branchKeys(action: string) {
    return getBuilderActionBranchKeys(action);
  }

  /**
   * Builds a human-readable description for an action from its data.
   *
   * @since 2.0.0
   * @param {string} action Action slug.
   * @param {Record<string, unknown>} data Action data payload.
   * @returns {string} The generated description.
   */
  function description(action: string, data: Record<string, unknown>) {
    return buildActionDescription(action, data);
  }

  return {
    actions,
    byContext,
    get,
    defaults,
    settingsComponent,
    branchKeys,
    description,
    registerAction: registerBuilderAction,
    registerActions: registerBuilderActions,
    hydrateFromBackend: hydrateBuilderActionsFromBackend,
    setCatalog: setActionCatalog,
    getActionsForContext: getBuilderActionsByContext,
    revision,
  };
}
