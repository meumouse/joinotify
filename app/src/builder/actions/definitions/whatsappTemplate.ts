/**
 * whatsappTemplate.ts
 *
 * Builder action definition for the "WhatsApp: Template message" action, which
 * sends a Meta-approved template through the official Cloud API. Templates are
 * the only way to reach someone outside the 24-hour session window, which is
 * where business initiated workflows almost always run.
 *
 * @since 2.3.0
 */
import WhatsappTemplateSettings from '../settings/WhatsappTemplateSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { WHATSAPP_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Keeps the stored variable map to the shape the backend expects, so a value
 * typed in the panel round-trips through save and import unchanged.
 *
 * @since 2.3.0
 * @param {unknown} variables Raw variable map.
 * @returns {Array<Record<string, unknown>>} Normalized variable map.
 */
function normalizeVariables(variables: unknown): Array<Record<string, unknown>> {
  if (!Array.isArray(variables)) {
    return [];
  }

  return variables
    .filter((variable): variable is Record<string, unknown> => Boolean(variable) && typeof variable === 'object')
    .map((variable) => ({
      component: String(variable.component || 'body'),
      sub_type: String(variable.sub_type || ''),
      index: Number(variable.index || 0),
      key: String(variable.key || ''),
      value: String(variable.value || ''),
    }));
}

/**
 * Normalizes/serializes the WhatsApp template action payload.
 *
 * @since 2.3.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized WhatsApp template action data.
 */
function normalizeWhatsappTemplateData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || __('WhatsApp: Template message', textDomain)),
    description: String(data.description || ''),
    action: 'send_whatsapp_message_template',
    sender: String(data.sender || ''),
    receiver: String(data.receiver || '{{ wc_billing_phone }}'),
    template_name: String(data.template_name || ''),
    language: String(data.language || 'pt_BR'),
    variables: normalizeVariables(data.variables),
  };
}

export const whatsappTemplateDefinition: ActionDefinition = {
  action: 'send_whatsapp_message_template',
  title: __('WhatsApp: Template message', textDomain),
  description: __('Send an approved template through the official WhatsApp Cloud API.', textDomain),
  icon: 'message-rounded',
  iconSvg: WHATSAPP_ICON,
  category: 'messages',
  requiresSetting: 'enable_whatsapp_integration',
  hasSettings: true,
  priority: 105,
  isExpansible: false,
  defaultData: normalizeWhatsappTemplateData({}),
  settingsComponent: WhatsappTemplateSettings,
  normalizeData: normalizeWhatsappTemplateData,
  serializeData: normalizeWhatsappTemplateData,
  buildDescription: (data) => truncateDescription(String(data.template_name || __('WhatsApp template message', textDomain))),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'sender', label: __('Sender', textDomain) },
    { key: 'template_name', label: __('Template', textDomain) },
  ])),
};
