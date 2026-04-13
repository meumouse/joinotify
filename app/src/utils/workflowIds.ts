import { generateHexToken } from './random';

export function createWorkflowId(prefix: string): string {
  return `${prefix}_${generateHexToken(12)}`;
}

export function createWorkflowNodeId(type: string): string {
  const safeType = String(type || 'node').replace(/[^a-z0-9]+/gi, '_').toLowerCase();
  return createWorkflowId(`joinotify_${safeType}`);
}
