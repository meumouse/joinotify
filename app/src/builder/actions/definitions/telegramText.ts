/**
 * telegramText.ts
 *
 * Builder action definition for the "Telegram: message" action, which sends a
 * plain text message to a Telegram chat, group or channel through the bot
 * configured in the Telegram integration. Provides data normalization (chat id
 * receiver, message), description builder, and validation.
 *
 * @since 2.1.0
 */
import TelegramTextSettings from '../settings/TelegramTextSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { TELEGRAM_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the Telegram text message action payload, applying
 * defaults for the chat id (receiver) and message.
 *
 * @since 2.1.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized Telegram text message action data.
 */
function normalizeTelegramTextData(data: Record<string, unknown>): Record<string, unknown> {
  const message = String(data.message || '');

  return {
    title: String(data.title || __('Telegram: message', textDomain)),
    description: String(data.description || ''),
    action: 'send_telegram_message_text',
    receiver: String(data.receiver || ''),
    message,
  };
}

export const telegramTextDefinition: ActionDefinition = {
  action: 'send_telegram_message_text',
  title: __('Telegram: message', textDomain),
  description: __('Send a text message to a Telegram chat, group or channel.', textDomain),
  icon: 'message-rounded',
  iconSvg: TELEGRAM_ICON,
  category: 'messages',
  hasSettings: true,
  priority: 60,
  isExpansible: false,
  defaultData: normalizeTelegramTextData({}),
  settingsComponent: TelegramTextSettings,
  normalizeData: normalizeTelegramTextData,
  serializeData: normalizeTelegramTextData,
  buildDescription: (data) => truncateDescription(String(data.message || __('Telegram message', textDomain))),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'receiver', label: __('Chat id', textDomain) },
    { key: 'message', label: __('Message', textDomain) },
  ])),
};
