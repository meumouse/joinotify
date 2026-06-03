import WhatsappMediaSettings from '../settings/WhatsappMediaSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';

function normalizeWhatsappMediaData(data: Record<string, unknown>): Record<string, unknown> {
  const caption = String(data.caption || '');
  const mediaType = String(data.media_type || 'image');

  return {
    title: String(data.title || 'WhatsApp: Media message'),
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
  title: 'WhatsApp: Media message',
  description: 'Send a WhatsApp media message.',
  icon: 'image',
  hasSettings: true,
  priority: 99,
  isExpansible: false,
  defaultData: normalizeWhatsappMediaData({}),
  settingsComponent: WhatsappMediaSettings,
  normalizeData: normalizeWhatsappMediaData,
  serializeData: normalizeWhatsappMediaData,
  buildDescription: (data) => truncateDescription(
    String(data.caption || '') || `Media: ${String(data.media_type || 'image')}`,
  ),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'sender', label: 'Sender' },
    { key: 'media_url', label: 'Media URL' },
  ])),
};
