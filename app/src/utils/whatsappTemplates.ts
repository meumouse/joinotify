/**
 * whatsappTemplates.ts
 *
 * Shapes and pure helpers for the Meta-approved WhatsApp message templates of
 * the connected account, shared by the builder's template action and the test
 * message of the settings.
 *
 * Besides the `{{...}}` tokens, a template may need values it has no token
 * for: the file or location of the header, the coupon of a copy-code button,
 * the login code of an authentication button and the expiration of a
 * limited-time offer. Those slots carry a `format` (or a button sub-type) and
 * get their own label and hint. A carousel lists the slots of each card, which
 * carry the card position in `card`.
 *
 * @since 2.8.0
 */
import { __, textDomain } from './i18n';

export interface TemplateVariable {
  card?: number;
  component: string;
  sub_type: string;
  index: number;
  key: string;
  label?: string;
  format?: string;
  value?: string;
}

export interface TemplateButton {
  type: string;
  text: string;
  url: string;
}

export interface TemplateCard {
  header_format: string;
  body: string;
  buttons: TemplateButton[];
}

export interface RemoteTemplate {
  name: string;
  language: string;
  status: string;
  category: string;
  header_format?: string;
  header: string;
  body: string;
  footer: string;
  buttons?: TemplateButton[];
  cards?: TemplateCard[];
  variables: TemplateVariable[];
}

export const MEDIA_FORMATS = ['image', 'video', 'document'];

/**
 * Carousel card a slot belongs to, -1 outside a carousel.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {number}
 */
export function cardOf(variable: TemplateVariable): number {
  return variable.card === undefined || variable.card === null ? -1 : Number(variable.card);
}

/**
 * Whether a slot holds the file of a media header.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {boolean}
 */
export function isMediaHeader(variable: TemplateVariable): boolean {
  return variable.component === 'header' && MEDIA_FORMATS.includes(variable.format || '');
}

/**
 * Whether a slot must have a value for Meta to accept the template.
 *
 * The login-code button repeats the body code when left empty, a quick-reply
 * payload is optional, and so are the name and address of a location header.
 * Every other slot is a parameter Meta counts.
 *
 * @since 2.8.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {boolean}
 */
export function isRequiredSlot(variable: TemplateVariable): boolean {
  if (variable.format === 'otp' || variable.sub_type === 'quick_reply') {
    return false;
  }

  return !(variable.format === 'location' && ['name', 'address'].includes(variable.key));
}

/**
 * Build the variable map of a template, keeping any value already mapped to
 * the same slot.
 *
 * @since 2.3.0
 * @version 2.8.0
 * @param {RemoteTemplate} template Template whose variables should be mapped.
 * @param {TemplateVariable[]} current Variables mapped so far.
 * @returns {TemplateVariable[]} The variable map to store.
 */
export function buildVariableMap(template: RemoteTemplate, current: TemplateVariable[] = []): TemplateVariable[] {
  const previousList = Array.isArray(current) ? current : [];

  return (template.variables || []).map((variable) => {
    // Token-less slots share an empty key, so the sub-type and format tell a
    // coupon from a login code on the same button position.
    const previous = previousList.find(
      (item) =>
        item.component === variable.component &&
        item.key === variable.key &&
        Number(item.index) === Number(variable.index) &&
        cardOf(item) === cardOf(variable) &&
        (item.sub_type || '') === (variable.sub_type || '') &&
        (item.format || '') === (variable.format || '')
    );

    return {
      card: cardOf(variable),
      component: variable.component,
      sub_type: variable.sub_type || '',
      index: Number(variable.index || 0),
      key: variable.key,
      label: variable.label || '',
      format: variable.format || '',
      value: previous?.value || '',
    };
  });
}

/**
 * Warning for a template status Meta refuses at send time, empty when it can go out.
 *
 * @since 2.8.0
 * @param {string} status Template status as synced from Meta.
 * @returns {string}
 */
export function templateStatusWarning(status: string): string {
  switch (String(status || '').toUpperCase()) {
    case 'PENDING':
      return __('This template is still awaiting Meta approval and cannot be delivered yet.', textDomain);
    case 'PAUSED':
      return __('Meta paused this template because of low quality. It will not be delivered until it recovers.', textDomain);
    case 'DISABLED':
      return __('Meta disabled this template. It can no longer be sent.', textDomain);
    case 'REJECTED':
      return __('Meta rejected this template.', textDomain);
    default:
      return '';
  }
}

/**
 * Name of a variable slot within its message or card.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {string}
 */
function slotLabel(variable: TemplateVariable): string {
  if (variable.component === 'header' && variable.format === 'location') {
    switch (variable.key) {
      case 'latitude':
        return `${__('Header location', textDomain)} · ${__('Latitude', textDomain)}`;
      case 'longitude':
        return `${__('Header location', textDomain)} · ${__('Longitude', textDomain)}`;
      case 'name':
        return `${__('Header location', textDomain)} · ${__('Name', textDomain)}`;
      default:
        return `${__('Header location', textDomain)} · ${__('Address', textDomain)}`;
    }
  }

  if (variable.component === 'limited_time_offer') {
    return __('Offer expiration', textDomain);
  }

  if (variable.component === 'button' && variable.sub_type === 'quick_reply') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · ${__('Reply payload', textDomain)}`;
  }

  if (isMediaHeader(variable)) {
    switch (variable.format) {
      case 'video':
        return __('Header video', textDomain);
      case 'document':
        return __('Header document', textDomain);
      default:
        return __('Header image', textDomain);
    }
  }

  if (variable.component === 'button' && variable.sub_type === 'copy_code') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · ${__('Coupon code', textDomain)}`;
  }

  if (variable.component === 'button' && variable.format === 'otp') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · ${__('Verification code', textDomain)}`;
  }

  if (variable.component === 'button') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · {{${variable.key}}}`;
  }

  if (variable.component === 'header') {
    return `${__('Header', textDomain)} · {{${variable.key}}}`;
  }

  return `${__('Body', textDomain)} · {{${variable.key}}}`;
}

/**
 * Human-readable name of a variable slot, prefixed with its carousel card.
 *
 * @since 2.3.0
 * @version 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {string} Label shown above the value field.
 */
export function variableLabel(variable: TemplateVariable): string {
  const card = cardOf(variable);

  return card >= 0 ? `${__('Card', textDomain)} ${card + 1} · ${slotLabel(variable)}` : slotLabel(variable);
}

/**
 * Example shown inside the field of a variable slot.
 *
 * @since 2.6.0
 * @version 2.8.0
 * @param {TemplateVariable} variable Variable slot.
 * @param {boolean} [withPlaceholders] Whether the field also takes placeholders.
 * @returns {string}
 */
export function variablePlaceholder(variable: TemplateVariable, withPlaceholders = true): string {
  if (isMediaHeader(variable)) {
    return withPlaceholders ? __('https://example.com/file.jpg or a placeholder', textDomain) : 'https://example.com/file.jpg';
  }

  if (variable.sub_type === 'copy_code') {
    return withPlaceholders ? __('BLACK20 or a placeholder', textDomain) : 'BLACK20';
  }

  if (variable.format === 'otp') {
    return __('Leave empty to repeat the code from the body', textDomain);
  }

  if (variable.format === 'location') {
    if (['latitude', 'longitude'].includes(variable.key)) {
      return withPlaceholders ? __('-23.5505 or a placeholder', textDomain) : '-23.5505';
    }

    return __('Optional', textDomain);
  }

  if (variable.component === 'limited_time_offer') {
    return withPlaceholders ? __('2026-12-31 23:59, +2 days or a placeholder', textDomain) : __('2026-12-31 23:59 or +2 days', textDomain);
  }

  if (variable.sub_type === 'quick_reply') {
    return __('Optional: sent back when the button is tapped', textDomain);
  }

  if (variable.sub_type === 'url') {
    return withPlaceholders ? __('End of the link, e.g. {{ wc_payment_url | remove_home_url }}', textDomain) : __('End of the link, e.g. order/123', textDomain);
  }

  return withPlaceholders ? __('{{ wc_billing_first_name }} or a fixed value', textDomain) : __('Value for this variable', textDomain);
}

/**
 * Fill the `{{key}}` tokens of a template text with the values typed so far.
 *
 * Only the slots of the given component outside a carousel take part; a token
 * whose value is still empty stays as written, so the preview shows what is
 * missing.
 *
 * @since 2.8.0
 * @param {string} text Header or body text of the template.
 * @param {string} component `header` or `body`.
 * @param {TemplateVariable[]} variables Variable map with the values.
 * @returns {string}
 */
export function fillTemplateText(text: string, component: string, variables: TemplateVariable[] = []): string {
  const values = new Map<string, string>();

  (Array.isArray(variables) ? variables : []).forEach((variable) => {
    const value = String(variable.value || '').trim();

    if (variable.component === component && cardOf(variable) < 0 && variable.key && value) {
      values.set(String(variable.key), value);
    }
  });

  return String(text || '').replace(/\{\{\s*([^}\s]+)\s*\}\}/g, (token, key) => values.get(key) ?? token);
}
