/**
 * resendEmail.ts
 *
 * Builder action definition for the "Send e-mail (Resend)" action, which sends
 * a transactional e-mail through the Resend integration. Provides data
 * normalization (recipient, subject, message), description builder, and
 * validation.
 *
 * @since 2.1.0
 */
import ResendEmailSettings from '../settings/ResendEmailSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { RESEND_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the Resend e-mail action payload, applying defaults for
 * the recipient (receiver), subject and message body.
 *
 * @since 2.1.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized Resend e-mail action data.
 */
function normalizeResendEmailData(data: Record<string, unknown>): Record<string, unknown> {
  const message = String(data.message || '');

  return {
    title: String(data.title || __('Send e-mail (Resend)', textDomain)),
    description: String(data.description || ''),
    action: 'send_resend_email',
    receiver: String(data.receiver || ''),
    subject: String(data.subject || ''),
    message,
  };
}

export const resendEmailDefinition: ActionDefinition = {
  action: 'send_resend_email',
  title: __('Send e-mail (Resend)', textDomain),
  description: __('Send an e-mail notification through Resend.', textDomain),
  icon: 'envelope',
  iconSvg: RESEND_ICON,
  category: 'messages',
  hasSettings: true,
  priority: 70,
  isExpansible: false,
  defaultData: normalizeResendEmailData({}),
  settingsComponent: ResendEmailSettings,
  normalizeData: normalizeResendEmailData,
  serializeData: normalizeResendEmailData,
  buildDescription: (data) => truncateDescription(String(data.subject || data.message || __('Resend e-mail', textDomain))),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'receiver', label: __('Recipient', textDomain) },
    { key: 'subject', label: __('Subject', textDomain) },
    { key: 'message', label: __('Message', textDomain) },
  ])),
};
