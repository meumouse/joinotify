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
  registerBuilderAction,
  registerBuilderActions,
  setActionCatalog,
} from '../registry/actionRegistry';
import type { ActionDefinition } from '../registry/types';

export function useActionRegistry() {
  ensureActionRegistry();

  const actions = computed<ActionDefinition[]>(() => getActionCatalog());

  function byContext(context: string) {
    return getActionsForContext(context);
  }

  function get(action: string) {
    return getBuilderAction(action);
  }

  function defaults(action: string) {
    return getBuilderActionDefaults(action);
  }

  function settingsComponent(action: string) {
    return getBuilderActionSettingsComponent(action);
  }

  function branchKeys(action: string) {
    return getBuilderActionBranchKeys(action);
  }

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
  };
}
