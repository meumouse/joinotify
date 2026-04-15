import SnippetPhpSettings from '../settings/SnippetPhpSettings.vue';
import { describeSnippetAction, truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';

function normalizeSnippetPhpData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || 'Snippet PHP'),
    description: String(data.description || ''),
    action: 'snippet_php',
    snippet_php: String(data.snippet_php || ''),
    settings: data.settings && typeof data.settings === 'object' ? data.settings : {},
  };
}

export const snippetPhpDefinition: ActionDefinition = {
  action: 'snippet_php',
  title: 'Snippet PHP',
  description: 'Run a PHP snippet during the workflow.',
  icon: 'code',
  hasSettings: true,
  priority: 80,
  isExpansible: true,
  defaultData: normalizeSnippetPhpData({}),
  settingsComponent: SnippetPhpSettings,
  settingsSchema: [
    { key: 'snippet_php', label: 'PHP snippet', component: 'code', required: true },
  ],
  normalizeData: normalizeSnippetPhpData,
  serializeData: normalizeSnippetPhpData,
  buildDescription: (data) => truncateDescription(describeSnippetAction(data)),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'snippet_php', label: 'PHP snippet' },
  ])),
};
