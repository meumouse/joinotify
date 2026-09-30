/**
 * labels.ts
 *
 * Labels and colors of the values the contact base carries (consent, source,
 * activity, field types, tag palette), shared by the tabs of the
 * "Audiences & Contacts" screen.
 *
 * @since 2.5.0
 */
import { __, textDomain } from '../../utils/i18n';

export interface Option {
  label: string;
  value: string;
}

/**
 * Marketing consent states.
 *
 * @since 2.5.0
 * @returns {Option[]} The states, in display order.
 */
export function consentOptions(): Option[] {
  return [
    { value: 'opted_in', label: __('Opted in', textDomain) },
    { value: 'unknown', label: __('Unknown', textDomain) },
    { value: 'opted_out', label: __('Opted out', textDomain) },
  ];
}

/**
 * How a contact entered the base.
 *
 * @since 2.5.0
 * @returns {Option[]} The sources.
 */
export function sourceOptions(): Option[] {
  return [
    { value: 'site', label: __('This site', textDomain) },
    { value: 'manual', label: __('Added by hand', textDomain) },
    { value: 'import', label: __('Import', textDomain) },
    { value: 'api', label: __('API', textDomain) },
    { value: 'inbound', label: __('Wrote to you', textDomain) },
    { value: 'flow', label: __('Flow', textDomain) },
    { value: 'webhook', label: __('Webhook', textDomain) },
  ];
}

/**
 * Label of an option value, or the value itself.
 *
 * @since 2.5.0
 * @param {Option[]} options Options.
 * @param {string} value Value.
 * @returns {string} The label.
 */
export function labelOf(options: Option[], value: string): string {
  return options.find((option) => option.value === value)?.label || value || '—';
}

/**
 * Badge classes of a consent state.
 *
 * @since 2.5.0
 * @param {string} status Consent state.
 * @returns {string} Tailwind classes.
 */
export function consentBadgeClass(status: string): string {
  const classes: Record<string, string> = {
    opted_in: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    opted_out: 'bg-rose-50 text-rose-700 ring-rose-200',
  };

  return classes[status] || 'bg-slate-50 text-slate-600 ring-slate-200';
}

/**
 * Colors of the panel's tag palette, by name.
 *
 * @since 2.5.0
 */
export const TAG_COLORS: Record<string, string> = {
  gray: 'bg-slate-100 text-slate-700 ring-slate-200',
  red: 'bg-red-50 text-red-700 ring-red-200',
  orange: 'bg-orange-50 text-orange-700 ring-orange-200',
  amber: 'bg-amber-50 text-amber-700 ring-amber-200',
  yellow: 'bg-yellow-50 text-yellow-800 ring-yellow-200',
  lime: 'bg-lime-50 text-lime-700 ring-lime-200',
  green: 'bg-green-50 text-green-700 ring-green-200',
  emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  teal: 'bg-teal-50 text-teal-700 ring-teal-200',
  cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
  sky: 'bg-sky-50 text-sky-700 ring-sky-200',
  blue: 'bg-blue-50 text-blue-700 ring-blue-200',
  indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
  violet: 'bg-violet-50 text-violet-700 ring-violet-200',
  purple: 'bg-purple-50 text-purple-700 ring-purple-200',
  pink: 'bg-pink-50 text-pink-700 ring-pink-200',
};

/**
 * Chip classes of a tag color; unknown colors fall back to gray.
 *
 * @since 2.5.0
 * @param {string|null} color Palette name.
 * @returns {string} Tailwind classes.
 */
export function tagClass(color?: string | null): string {
  return TAG_COLORS[color || ''] || TAG_COLORS.gray;
}

/**
 * What happened to a contact, by activity type.
 *
 * @since 2.5.0
 * @param {string} type Activity type.
 * @returns {string} The label.
 */
export function activityLabel(type: string): string {
  const labels: Record<string, string> = {
    created: __('Created', textDomain),
    updated: __('Edited', textDomain),
    imported: __('Imported', textDomain),
    tag_added: __('Tag added', textDomain),
    tag_removed: __('Tag removed', textDomain),
    opted_in: __('Opted in', textDomain),
    opted_out: __('Opted out', textDomain),
    merged: __('Merged with a duplicate', textDomain),
    broadcast_clicked: __('Clicked a campaign', textDomain),
  };

  return labels[type] || type;
}

/**
 * Custom field types.
 *
 * @since 2.5.0
 * @returns {Option[]} The types.
 */
export function fieldTypeOptions(): Option[] {
  return [
    { value: 'text', label: __('Text', textDomain) },
    { value: 'number', label: __('Number', textDomain) },
    { value: 'date', label: __('Date', textDomain) },
    { value: 'datetime', label: __('Date and time', textDomain) },
    { value: 'boolean', label: __('Yes / no', textDomain) },
    { value: 'select', label: __('Single choice', textDomain) },
    { value: 'multi_select', label: __('Multiple choice', textDomain) },
    { value: 'email', label: __('E-mail', textDomain) },
    { value: 'url', label: __('URL', textDomain) },
    { value: 'phone', label: __('Phone', textDomain) },
  ];
}

/**
 * Formats a platform timestamp in the viewer's locale.
 *
 * @since 2.5.0
 * @param {string|null} value ISO date.
 * @param {string} [locale] BCP 47 locale.
 * @returns {string} The formatted date, or a dash.
 */
export function formatDate(value?: string | null, locale = ''): string {
  if (!value) {
    return '—';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString(locale || undefined, { dateStyle: 'short', timeStyle: 'short' });
}

/**
 * Formats a stored phone (E.164 without `+`) for display.
 *
 * @since 2.5.0
 * @param {string|null} phone Digits.
 * @returns {string} The phone with its `+`, or a dash.
 */
export function formatPhone(phone?: string | null): string {
  return phone ? `+${String(phone).replace(/^\+/, '')}` : '—';
}
