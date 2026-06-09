import WhatsappTextSettings from '../settings/WhatsappTextSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { __, textDomain } from '../../../utils/i18n';

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
