/**
 * workflowIds.ts
 *
 * Generators for unique workflow and workflow-node identifiers, combining a
 * caller-provided prefix with a random hex token.
 *
 * @since 2.0.0
 */
import { generateHexToken } from './random';

/**
 * Creates a unique workflow ID from a prefix and a random hex token.
 *
 * @since 2.0.0
 * @param {string} prefix The ID prefix.
 * @returns {string} The generated workflow ID.
 */
export function createWorkflowId(prefix: string): string {
  return `${prefix}_${generateHexToken(12)}`;
}

/**
 * Creates a unique node ID derived from a sanitized node type.
 *
 * @since 2.0.0
 * @param {string} type The node type used to build the ID.
 * @returns {string} The generated node ID.
 */
export function createWorkflowNodeId(type: string): string {
  const safeType = String(type || 'node').replace(/[^a-z0-9]+/gi, '_').toLowerCase();
  return createWorkflowId(`joinotify_${safeType}`);
}
