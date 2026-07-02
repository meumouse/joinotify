/**
 * actionDescription.ts
 *
 * Pure helpers that build the short, human-readable summary shown on each
 * builder node for its action data. Covers time-delay, condition, PHP snippet,
 * stop and generic fallback actions, resolving translated labels from the
 * conditions catalog cache.
 *
 * @since 2.0.0
 */
import { __, _n, sprintf, textDomain } from '../../../utils/i18n';
import {
  findCatalogCondition,
  getConditionOperatorLabel,
  getConditionOptionLabel,
  type CatalogCondition,
} from './conditionsCatalog';

// Operators that compare against no value (mirrors ConditionSettings.vue).
const OPERATORS_WITHOUT_VALUE = ['empty', 'not_empty'];

/**
 * Coerce any value to a trimmed string with collapsed whitespace.
 *
 * @since 2.0.0
 * @param {unknown} value Value to clean.
 * @returns {string} Normalized string.
 */
function cleanText(value: unknown): string {
  return String(value ?? '').replace(/\s+/g, ' ').trim();
}

/**
 * Join non-empty segments with a middle-dot separator.
 *
 * @since 2.0.0
 * @param {Array} parts Segments to join.
 * @returns {string} Joined string.
 */
function joinSegments(parts: string[]): string {
  return parts.filter(Boolean).join(' · ');
}

/**
 * Type guard for a plain, non-array object.
 *
 * @since 2.0.0
 * @param {unknown} value Value to test.
 * @returns {boolean} True when the value is a plain record.
 */
function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Join non-empty items with a comma separator.
 *
 * @since 2.0.0
 * @param {Array} items Items to join.
 * @returns {string} Comma-separated list.
 */
function formatList(items: string[]): string {
  return items.filter(Boolean).join(', ');
}

/**
 * Build the translated, pluralized "amount + period" phrase for a delay (e.g.
 * "2 dias", "1 hora"). Falls back to the raw "amount period" text for unknown
 * periods so nothing is silently dropped.
 *
 * @since 2.0.0
 * @param {string} amount The delay amount as text.
 * @param {string} period The delay period key (seconds, minute, hours, …).
 * @returns {string} The localized delay offset.
 */
function describeDelayOffset(amount: string, period: string): string {
  if (!amount) {
    return period;
  }

  const count = Number.parseInt(amount, 10);

  if (!Number.isFinite(count)) {
    return `${amount} ${period}`;
  }

  switch (period) {
    case 'seconds':
      return sprintf(_n('%d second', '%d seconds', count, textDomain), count);
    case 'minute':
      return sprintf(_n('%d minute', '%d minutes', count, textDomain), count);
    case 'hours':
      return sprintf(_n('%d hour', '%d hours', count, textDomain), count);
    case 'day':
      return sprintf(_n('%d day', '%d days', count, textDomain), count);
    case 'week':
      return sprintf(_n('%d week', '%d weeks', count, textDomain), count);
    case 'month':
      return sprintf(_n('%d month', '%d months', count, textDomain), count);
    case 'year':
      return sprintf(_n('%d year', '%d years', count, textDomain), count);
    default:
      return `${amount} ${period}`;
  }
}

/**
 * Build the summary for a time-delay action (period, date or scheduled).
 *
 * @since 2.0.0
 * @param {Object} data Time-delay action data.
 * @returns {string} Human-readable delay summary.
 */
export function describeTimeDelayAction(data: Record<string, unknown>): string {
  const mode = cleanText(data.delay_type || 'period');

  if (mode === 'date') {
    return joinSegments([
      __('Delay', textDomain),
      cleanText(data.date_value || ''),
      cleanText(data.time_value || ''),
    ]) || __('Delay until a fixed date', textDomain);
  }

  if (mode === 'scheduled') {
    const amount = cleanText(data.delay_value || '');
    const period = cleanText(data.delay_period || 'day');
    const time = cleanText(data.time_value || '');
    const offset = describeDelayOffset(amount, period);

    return joinSegments([
      __('Delay', textDomain),
      offset,
      time ? `${__('at', textDomain)} ${time}` : '',
    ]);
  }

  const amount = cleanText(data.delay_value || '');
  const period = cleanText(data.delay_period || 'minute');
  return joinSegments([__('Delay', textDomain), describeDelayOffset(amount, period)]);
}

/**
 * Resolve the human-readable comparison value for a condition.
 *
 * Resolves product names, a status label, Yes/No, or the raw value using the
 * catalog metadata.
 *
 * @since 2.0.0
 * @param {Object} data Condition action data.
 * @param {CatalogCondition|null} condition Matched catalog condition, if any.
 * @returns {string} Display value for the condition.
 */
function describeConditionValue(data: Record<string, unknown>, condition: CatalogCondition | null): string {
  const valueType = String(condition?.value_type || 'text');

  if (valueType === 'products') {
    const content = isRecord(data.condition_content) ? data.condition_content : {};
    const products = Array.isArray(data.products)
      ? data.products
      : (Array.isArray(content.products) ? content.products : []);
    const titles = products
      .map((item) => cleanText(isRecord(item) ? item.title : ''))
      .filter(Boolean);

    return formatList(titles);
  }

  const content = isRecord(data.condition_content) ? data.condition_content : {};
  const raw = cleanText(data.value ?? data.value_text ?? content.value ?? content.value_text ?? '');

  if (!raw) {
    return '';
  }

  if (Array.isArray(condition?.options) && condition!.options!.length) {
    return cleanText(getConditionOptionLabel(condition, raw));
  }

  if (valueType === 'boolean') {
    if (raw === 'true') {
      return __('Yes', textDomain);
    }

    if (raw === 'false') {
      return __('No', textDomain);
    }
  }

  return raw;
}

/**
 * Build the summary for a condition action (e.g. "Order status: Is equal to
 * Processing"), falling back to a key-based label when the catalog is
 * unavailable.
 *
 * @since 2.0.0
 * @param {Object} data Condition action data.
 * @returns {string} Human-readable condition summary.
 */
export function describeConditionAction(data: Record<string, unknown>): string {
  const conditionKey = cleanText(data.condition || '');
  const operator = cleanText(data.condition_type || '');
  const catalogCondition = conditionKey ? findCatalogCondition(conditionKey) : null;
  const title = cleanText(catalogCondition?.title || '');

  // Catalog unavailable or unknown condition: fall back to the compact,
  // key-based label so the node still shows something meaningful.
  if (!title) {
    const field = cleanText(data.field_id || data.meta_key || '');

    return joinSegments([
      __('Condition', textDomain),
      field,
      conditionKey || 'condition',
      operator,
    ]);
  }

  const operatorLabel = operator ? cleanText(getConditionOperatorLabel(operator)) : '';
  const value = OPERATORS_WITHOUT_VALUE.includes(operator)
    ? ''
    : describeConditionValue(data, catalogCondition);

  // e.g. "Order status: Is equal to Processing"
  //      "Purchased products: Contains Shirt, Hat"
  const tail = [operatorLabel, value].filter(Boolean).join(' ');

  return tail ? `${title}: ${tail}` : title;
}

/**
 * Build the summary for a PHP snippet action, previewing the first characters
 * of the code.
 *
 * @since 2.0.0
 * @param {Object} data Snippet action data.
 * @returns {string} Human-readable snippet summary.
 */
export function describeSnippetAction(data: Record<string, unknown>): string {
  const snippet = cleanText(data.snippet_php || '');

  if (!snippet) {
    return __('PHP snippet', textDomain);
  }

  return joinSegments([__('PHP snippet', textDomain), snippet.slice(0, 48)]);
}

/**
 * Build the summary for a stop action.
 *
 * @since 2.0.0
 * @returns {string} Human-readable stop summary.
 */
export function describeStopAction(): string {
  return __('Stops the workflow immediately', textDomain);
}

/**
 * Build a generic fallback summary from the action data or its title and
 * description.
 *
 * @since 2.0.0
 * @param {string} title Action title.
 * @param {string} description Action description.
 * @param {Object} data Action data.
 * @returns {string} Human-readable fallback summary.
 */
export function describeFallbackAction(title: string, description: string, data: Record<string, unknown>): string {
  const candidate = cleanText(data.description || data.message || data.summary || '');

  if (candidate) {
    return candidate;
  }

  return joinSegments([cleanText(title), cleanText(description)]);
}

/**
 * Truncate a description to a maximum length, appending an ellipsis when cut.
 *
 * @since 2.0.0
 * @param {string} value Text to truncate.
 * @param {number} maxLength Maximum length before truncation.
 * @returns {string} Truncated text.
 */
export function truncateDescription(value: string, maxLength = 120): string {
  const text = cleanText(value);

  if (text.length <= maxLength) {
    return text;
  }

  return `${text.slice(0, maxLength - 1).trimEnd()}…`;
}
