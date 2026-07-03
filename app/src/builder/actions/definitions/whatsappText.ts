/**
 * whatsappText.ts
 *
 * Builder action definition for the "WhatsApp: Text message" action, which
 * sends a plain WhatsApp text message. Provides data normalization (sender,
 * receiver, message), description builder, and validation for the sender and
 * message.
 *
 * @since 2.0.0
 */
import WhatsappTextSettings from '../settings/WhatsappTextSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { WHATSAPP_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the WhatsApp text message action payload, applying
 * defaults for sender, receiver, and message.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized WhatsApp text message action data.
 */
function normalizeWhatsappTextData(data: Record<string, unknown>): Record<string, unknown> {
  const message = String(data.message || '');

  return {
    title: String(data.title || __('WhatsApp: Text message', textDomain)),
    description: String(data.description || ''),
    action: 'send_whatsapp_message_text',
    sender: String(data.sender || ''),
    receiver: String(data.receiver || '{{ wc_billing_phone }}'),
    message,
  };
}

export const whatsappTextDefinition: ActionDefinition = {
  action: 'send_whatsapp_message_text',
  title: __('WhatsApp: Text message', textDomain),
  description: __('Send a WhatsApp text message.', textDomain),
  icon: 'message-rounded',
  iconSvg: WHATSAPP_ICON,
  category: 'messages',
  hasSettings: true,
  priority: 100,
  isExpansible: false,
  defaultData: normalizeWhatsappTextData({}),
  settingsComponent: WhatsappTextSettings,
  normalizeData: normalizeWhatsappTextData,
  serializeData: normalizeWhatsappTextData,
  buildDescription: (data) => truncateDescription(String(data.message || __('WhatsApp text message', textDomain))),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'sender', label: __('Sender', textDomain) },
    { key: 'message', label: __('Message', textDomain) },
  ])),
};
