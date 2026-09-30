/**
 * audienceFilter.ts
 *
 * The audience filter tree (`{ op: 'and'|'or', rules: [...] }`) as the filter
 * builder edits it: what a new condition looks like, which operators each
 * condition offers (from the platform's `/audiences/schema`), which operators
 * take no value, and the limits the platform enforces.
 *
 * Condition types the builder does not draw (`engagement`,
 * `campaign_frequency`, `tag_added`…) are kept as they are, so editing an
 * audience built in the Joinotify panel never loses them.
 *
 * @since 2.5.0
 */
import { __, textDomain } from '../../utils/i18n';

export interface FilterRule {
  type?: string;
  op: string;
  key?: string;
  value?: unknown;
  rules?: FilterRule[];
  [extra: string]: unknown;
}

export interface FilterGroup {
  op: 'and' | 'or';
  rules: FilterRule[];
}

export interface AudienceSchema {
  nativeFields?: string[];
  operators?: Record<string, string[]>;
}

/** Deepest group nesting the platform accepts. */
export const MAX_DEPTH = 3;

/** Most conditions the platform accepts, all groups together. */
export const MAX_RULES = 30;

/** Condition types the builder draws; any other is shown read only. */
export const EDITABLE_TYPES = ['field', 'tag', 'consent', 'search'];

/** Operators that compare with nothing. */
const NO_VALUE_OPERATORS = ['is_set', 'is_not_set', 'exists', 'not_exists', 'is_empty', 'is_not_empty', 'empty', 'not_empty', 'is_true', 'is_false', 'set', 'not_set'];

/**
 * Fallback operators when the schema does not list a type.
 *
 * `consent` follows the platform's documented example; the others are a best
 * guess the preview validates before anything is saved.
 */
const FALLBACK_OPERATORS: Record<string, string[]> = {
  consent: ['is', 'is_not'],
  tag: ['has', 'not_has'],
  search: ['contains'],
  text: ['eq', 'neq', 'contains', 'is_set', 'is_not_set'],
};

/**
 * Whether a rule is a nested group.
 *
 * @since 2.5.0
 * @param {FilterRule} rule A rule of the tree.
 * @returns {boolean} True for a group.
 */
export function isGroup(rule: FilterRule): boolean {
  return Boolean(rule) && Array.isArray(rule.rules) && !rule.type;
}

/**
 * Counts the conditions of a tree, nested groups included.
 *
 * @since 2.5.0
 * @param {FilterGroup|FilterRule} group A group.
 * @returns {number} How many conditions it holds.
 */
export function countRules(group: FilterGroup | FilterRule): number {
  return (group.rules || []).reduce((total: number, rule: FilterRule) => total + (isGroup(rule) ? countRules(rule) : 1), 0);
}

/**
 * An empty group.
 *
 * @since 2.5.0
 * @param {'and'|'or'} [op] Group operator.
 * @returns {FilterGroup} The group.
 */
export function emptyGroup(op: 'and' | 'or' = 'and'): FilterGroup {
  return { op, rules: [] };
}

/**
 * A new condition of a type, with its first operator.
 *
 * @since 2.5.0
 * @param {string} type Condition type.
 * @param {AudienceSchema} schema Filter schema.
 * @returns {FilterRule} The condition.
 */
export function newRule(type: string, schema: AudienceSchema): FilterRule {
  if (type === 'consent') {
    return { type, op: operatorsFor('consent', schema)[0], value: 'opted_in' };
  }

  if (type === 'tag') {
    return { type, op: operatorsFor('tag', schema)[0], value: '' };
  }

  if (type === 'search') {
    return { type, op: operatorsFor('search', schema)[0], value: '' };
  }

  return { type: 'field', key: '', op: operatorsFor('text', schema)[0], value: '' };
}

/**
 * Operators of a condition or field type: the schema's, or the fallback.
 *
 * @since 2.5.0
 * @param {string} type Condition or field type.
 * @param {AudienceSchema} schema Filter schema.
 * @returns {string[]} Operator codes.
 */
export function operatorsFor(type: string, schema: AudienceSchema): string[] {
  const listed = schema?.operators?.[type];

  if (Array.isArray(listed) && listed.length) {
    return listed;
  }

  return FALLBACK_OPERATORS[type] || FALLBACK_OPERATORS.text;
}

/**
 * The type of a native field, from its name.
 *
 * The schema lists native fields by name only; dates end in `_at`.
 *
 * @since 2.5.0
 * @param {string} name Native field.
 * @returns {string} The field type.
 */
export function nativeFieldType(name: string): string {
  return /_at$/.test(name) ? 'datetime' : 'text';
}

/**
 * Whether an operator compares with a value.
 *
 * @since 2.5.0
 * @param {string} op Operator code.
 * @returns {boolean} False for operators like "is set".
 */
export function takesValue(op: string): boolean {
  return !NO_VALUE_OPERATORS.includes(op);
}

/**
 * Label of an operator code; unknown codes show as they are.
 *
 * @since 2.5.0
 * @param {string} op Operator code.
 * @returns {string} The label.
 */
export function operatorLabel(op: string): string {
  const labels: Record<string, string> = {
    eq: __('is', textDomain),
    is: __('is', textDomain),
    neq: __('is not', textDomain),
    is_not: __('is not', textDomain),
    contains: __('contains', textDomain),
    not_contains: __('does not contain', textDomain),
    starts_with: __('starts with', textDomain),
    ends_with: __('ends with', textDomain),
    gt: __('is greater than', textDomain),
    gte: __('is at least', textDomain),
    lt: __('is less than', textDomain),
    lte: __('is at most', textDomain),
    before: __('is before', textDomain),
    after: __('is after', textDomain),
    in: __('is one of', textDomain),
    not_in: __('is none of', textDomain),
    has: __('has', textDomain),
    not_has: __('does not have', textDomain),
    is_set: __('is filled', textDomain),
    is_not_set: __('is empty', textDomain),
    exists: __('is filled', textDomain),
    not_exists: __('is empty', textDomain),
    is_empty: __('is empty', textDomain),
    is_not_empty: __('is filled', textDomain),
    is_true: __('is yes', textDomain),
    is_false: __('is no', textDomain),
  };

  return labels[op] || op;
}

/**
 * Label of a condition type the builder does not draw.
 *
 * @since 2.5.0
 * @param {string} type Condition type.
 * @returns {string} The label.
 */
export function ruleTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    field: __('Field', textDomain),
    tag: __('Tag', textDomain),
    tag_added: __('Tag added', textDomain),
    consent: __('Consent', textDomain),
    engagement: __('Engagement', textDomain),
    campaign_frequency: __('Campaign frequency', textDomain),
    search: __('Search', textDomain),
  };

  return labels[type] || type;
}

/**
 * Removes the conditions still missing what they need, so a half-typed
 * condition does not make the preview fail. Groups left empty go too.
 *
 * @since 2.5.0
 * @param {FilterGroup} group The tree.
 * @returns {FilterGroup} A copy with complete conditions only.
 */
export function completeRules(group: FilterGroup): FilterGroup {
  const rules = (group.rules || [])
    .map((rule) => (isGroup(rule) ? (completeRules(rule as unknown as FilterGroup) as unknown as FilterRule) : rule))
    .filter((rule) => {
      if (isGroup(rule)) {
        return (rule.rules || []).length > 0;
      }

      if (!EDITABLE_TYPES.includes(String(rule.type))) {
        return true;
      }

      if (rule.type === 'field' && !rule.key) {
        return false;
      }

      return !takesValue(rule.op) || (rule.value !== '' && rule.value !== undefined && rule.value !== null);
    });

  return { op: group.op, rules };
}
