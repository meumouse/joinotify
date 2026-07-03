/**
 * dynamicPlaceholder.ts
 *
 * Builder action definition for the "AI: Smart variable" action, which
 * generates a named value with AI so it can be reused as a placeholder in
 * later messages. Provides data normalization, description builder, and
 * validation for the variable name and prompt.
 *
 * @since 2.0.0
 */
import DynamicPlaceholderSettings from '../settings/DynamicPlaceholderSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { SMART_VARIABLE_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the smart-variable action payload, applying defaults
 * for the variable name and AI generation settings.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized smart-variable action data.
 */
function normalizeDynamicPlaceholderData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || __('AI: Smart variable', textDomain)),
    description: String(data.description || ''),
    action: 'dynamic_placeholder',
    var_name: String(data.var_name || ''),
    ai_prompt: String(data.ai_prompt || ''),
    ai_system: String(data.ai_system || ''),
    ai_model: String(data.ai_model || ''),
    ai_temperature: String(data.ai_temperature ?? ''),
  };
}

export const dynamicPlaceholderDefinition: ActionDefinition = {
  action: 'dynamic_placeholder',
  title: __('AI: Smart variable', textDomain),
  description: __('Generate a named value with AI and reuse it in later messages.', textDomain),
  icon: 'code',
  iconSvg: SMART_VARIABLE_ICON,
  category: 'ai',
  hasSettings: true,
  priority: 60,
  isExpansible: false,
  defaultData: normalizeDynamicPlaceholderData({}),
  settingsComponent: DynamicPlaceholderSettings,
  normalizeData: normalizeDynamicPlaceholderData,
  serializeData: normalizeDynamicPlaceholderData,
  buildDescription: (data) =>
    data.var_name
      ? `{{ ai:${String(data.var_name)} }}`
      : truncateDescription(String(data.ai_prompt || __('AI smart variable', textDomain))),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'var_name', label: __('Variable name', textDomain) },
    { key: 'ai_prompt', label: __('Prompt', textDomain) },
  ])),
};
