import WhatsappAiMessageSettings from '../settings/WhatsappAiMessageSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { __, textDomain } from '../../../utils/i18n';

function normalizeWhatsappAiMessageData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || __('WhatsApp: AI message', textDomain)),
    description: String(data.description || ''),
    action: 'send_whatsapp_ai_message',
    sender: String(data.sender || ''),
    receiver: String(data.receiver || '{{ wc_billing_phone }}'),
    ai_prompt: String(data.ai_prompt || ''),
    ai_system: String(data.ai_system || ''),
    ai_tone: String(data.ai_tone || 'friendly'),
    ai_length: String(data.ai_length || 'medium'),
    ai_model: String(data.ai_model || ''),
    ai_temperature: String(data.ai_temperature ?? ''),
  };
}

export const whatsappAiMessageDefinition: ActionDefinition = {
  action: 'send_whatsapp_ai_message',
  title: __('WhatsApp: AI message', textDomain),
  description: __('Generate a message with AI at trigger time and send it via WhatsApp.', textDomain),
  icon: 'message-rounded',
  category: 'ai',
  hasSettings: true,
  priority: 45,
  isExpansible: false,
  defaultData: normalizeWhatsappAiMessageData({}),
  settingsComponent: WhatsappAiMessageSettings,
  normalizeData: normalizeWhatsappAiMessageData,
  serializeData: normalizeWhatsappAiMessageData,
  buildDescription: (data) =>
    truncateDescription(String(data.ai_prompt || __('AI-generated WhatsApp message', textDomain))),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'sender', label: __('Sender', textDomain) },
    { key: 'ai_prompt', label: __('Prompt', textDomain) },
  ])),
};
