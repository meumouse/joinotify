/**
 * whatsappAiMessage.ts
 *
 * Builder action definition for the "WhatsApp: AI message" action, which
 * generates a message with AI at trigger time and sends it via WhatsApp.
 * Provides data normalization (sender, receiver, and AI settings), description
 * builder, and validation for the sender and prompt.
 *
 * @since 2.0.0
 */
import WhatsappAiMessageSettings from '../settings/WhatsappAiMessageSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { WHATSAPP_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the WhatsApp AI message action payload, applying
 * defaults for sender, receiver, and the AI generation settings.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized WhatsApp AI message action data.
 */
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
  iconSvg: WHATSAPP_ICON,
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
