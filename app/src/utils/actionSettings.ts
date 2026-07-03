/**
 * actionSettings.ts
 *
 * Helpers to decide whether an action/condition node still has required settings
 * to fill. Mirrors triggerSettings.ts: each action definition may expose a
 * `validate` function that returns one error string per missing required field
 * (e.g. the WhatsApp "sender"). Until those are filled the node is flagged on the
 * canvas so the user knows configuration is still pending.
 *
 * @since 2.0.0
 */
import type { WorkflowNode, WorkflowRegistryItem } from '../types/workflowBuilder';

/**
 * Whether a non-trigger node still needs its required settings to be configured.
 *
 * The action definition's `validate` returns one message per empty required
 * field; any message means the node is incomplete.
 */
export function actionNeedsSetup(
  node: WorkflowNode | null | undefined,
  definition: WorkflowRegistryItem | undefined,
): boolean {
  if (!node || typeof definition?.validate !== 'function') {
    return false;
  }

  const data = node.data && typeof node.data === 'object'
    ? (node.data as Record<string, unknown>)
    : {};

  return definition.validate(data).length > 0;
}
