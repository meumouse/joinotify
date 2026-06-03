import WhatsappTextSettings from '../settings/WhatsappTextSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';

function normalizeWhatsappTextData(data: Record<string, unknown>): Record<string, unknown> {
  const message = String(data.message || '');

  return {
    title: String(data.title || 'WhatsApp: Text message'),
    description: String(data.description || ''),
    action: 'send_whatsapp_message_text',
    sender: String(data.sender || ''),
    receiver: String(data.receiver || '{{ wc_billing_phone }}'),
    message,
  };
}

export const whatsappTextDefinition: ActionDefinition = {
  action: 'send_whatsapp_message_text',
  title: 'WhatsApp: Text message',
  description: 'Send a WhatsApp text message.',
  icon: 'message-rounded',
  hasSettings: true,
  priority: 100,
  isExpansible: false,
  defaultData: normalizeWhatsappTextData({}),
  settingsComponent: WhatsappTextSettings,
  normalizeData: normalizeWhatsappTextData,
  serializeData: normalizeWhatsappTextData,
  buildDescription: (data) => truncateDescription(String(data.message || 'WhatsApp text message')),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'sender', label: 'Sender' },
    { key: 'message', label: 'Message' },
  ])),
};
