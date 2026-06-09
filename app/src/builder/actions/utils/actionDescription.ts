import { __, textDomain } from '../../../utils/i18n';
import {
  findCatalogCondition,
  getConditionOperatorLabel,
  getConditionOptionLabel,
  type CatalogCondition,
} from './conditionsCatalog';

// Operators that compare against no value (mirrors ConditionSettings.vue).
const OPERATORS_WITHOUT_VALUE = ['empty', 'not_empty'];

function cleanText(value: unknown): string {
  return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function joinSegments(parts: string[]): string {
  return parts.filter(Boolean).join(' · ');
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

function formatList(items: string[]): string {
  return items.filter(Boolean).join(', ');
}

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
    const offset = amount ? `${amount} ${period}` : period;

    return joinSegments([
      __('Delay', textDomain),
      offset,
      time ? `${__('at', textDomain)} ${time}` : '',
    ]);
  }

  const amount = cleanText(data.delay_value || '');
  const period = cleanText(data.delay_period || 'minute');
  return joinSegments([__('Delay', textDomain), amount ? `${amount} ${period}` : period]);
}

// Resolve the human-readable comparison value for a condition (product names,
// status label, Yes/No, or the raw value) using the catalog metadata.
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

export function describeSnippetAction(data: Record<string, unknown>): string {
  const snippet = cleanText(data.snippet_php || '');

  if (!snippet) {
    return __('PHP snippet', textDomain);
  }

  return joinSegments([__('PHP snippet', textDomain), snippet.slice(0, 48)]);
}

export function describeStopAction(): string {
  return __('Stops the workflow immediately', textDomain);
}

export function describeFallbackAction(title: string, description: string, data: Record<string, unknown>): string {
  const candidate = cleanText(data.description || data.message || data.summary || '');

  if (candidate) {
    return candidate;
  }

  return joinSegments([cleanText(title), cleanText(description)]);
}

export function truncateDescription(value: string, maxLength = 120): string {
  const text = cleanText(value);

  if (text.length <= maxLength) {
    return text;
  }

  return `${text.slice(0, maxLength - 1).trimEnd()}…`;
}
