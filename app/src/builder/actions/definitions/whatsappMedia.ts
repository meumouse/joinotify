/**
 * whatsappMedia.ts
 *
 * Builder action definition for the "WhatsApp: Media message" action, which
 * sends a WhatsApp media message (image, etc.) with an optional caption.
 * Provides data normalization, description builder, and validation for the
 * sender and media URL.
 *
 * @since 2.0.0
 */
import WhatsappMediaSettings from '../settings/WhatsappMediaSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { WHATSAPP_ICON } from './actionIcons';
import { __, sprintf, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the WhatsApp media message action payload, applying
 * defaults for media type/URL, caption, sender, and receiver.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized WhatsApp media message action data.
 */
function normalizeWhatsappMediaData(data: Record<string, unknown>): Record<string, unknown> {
  const caption = String(data.caption || '');
  const mediaType = String(data.media_type || 'image');

  return {
    title: String(data.title || __('WhatsApp: Media message', textDomain)),
    description: String(data.description || ''),
    action: 'send_whatsapp_message_media',
    media_type: mediaType,
    media_url: String(data.media_url || ''),
    caption,
    sender: String(data.sender || ''),
    receiver: String(data.receiver || '{{ wc_billing_phone }}'),
  };
}

export const whatsappMediaDefinition: ActionDefinition = {
  action: 'send_whatsapp_message_media',
  title: __('WhatsApp: Media message', textDomain),
  description: __('Send a WhatsApp media message.', textDomain),
  icon: 'image',
  iconSvg: WHATSAPP_ICON,
  category: 'messages',
  hasSettings: true,
  priority: 99,
  isExpansible: false,
  defaultData: normalizeWhatsappMediaData({}),
  settingsComponent: WhatsappMediaSettings,
  normalizeData: normalizeWhatsappMediaData,
  serializeData: normalizeWhatsappMediaData,
  buildDescription: (data) => truncateDescription(
    String(data.caption || '') || sprintf(__('Media: %s', textDomain), String(data.media_type || 'image')),
  ),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'sender', label: __('Sender', textDomain) },
    { key: 'media_url', label: __('Media URL', textDomain) },
  ])),
};
