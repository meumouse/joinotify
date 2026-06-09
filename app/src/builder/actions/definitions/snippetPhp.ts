import SnippetPhpSettings from '../settings/SnippetPhpSettings.vue';
import { describeSnippetAction, truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { SNIPPET_PHP_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

function normalizeSnippetPhpData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || __('PHP Snippet', textDomain)),
    description: String(data.description || ''),
    action: 'snippet_php',
    snippet_php: String(data.snippet_php || ''),
    settings: data.settings && typeof data.settings === 'object' ? data.settings : {},
  };
}

export const snippetPhpDefinition: ActionDefinition = {
  action: 'snippet_php',
  title: __('PHP Snippet', textDomain),
  description: __('Run a PHP snippet during the workflow.', textDomain),
  icon: 'code',
  iconSvg: SNIPPET_PHP_ICON,
  category: 'advanced',
  hasSettings: true,
  priority: 80,
  isExpansible: true,
  defaultData: normalizeSnippetPhpData({}),
  settingsComponent: SnippetPhpSettings,
  settingsSchema: [
    { key: 'snippet_php', label: __('PHP snippet', textDomain), component: 'code', required: true },
  ],
  normalizeData: normalizeSnippetPhpData,
  serializeData: normalizeSnippetPhpData,
  buildDescription: (data) => truncateDescription(describeSnippetAction(data)),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'snippet_php', label: __('PHP snippet', textDomain) },
  ])),
};
