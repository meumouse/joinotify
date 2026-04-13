import type { ExportedWorkflowFile } from '../types/workflowBuilder';
import { normalizeWorkflowFile } from '../parsers/workflowParser';

export function serializeWorkflowFile(file: ExportedWorkflowFile): ExportedWorkflowFile {
  return normalizeWorkflowFile(file);
}

export function serializeWorkflowToJson(file: ExportedWorkflowFile): string {
  return JSON.stringify(serializeWorkflowFile(file), null, 2);
}
