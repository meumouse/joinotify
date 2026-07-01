/**
 * conditionsCatalog.ts
 *
 * Module-level mirror of the conditions catalog exposed by the backend
 * bootstrap (`bootstrap.conditions`). The catalog is the single source of truth
 * for translated condition titles, operator labels and value options.
 *
 * ConditionSettings.vue reads the catalog reactively from the store, but the
 * node-description builders (describeConditionAction) are pure functions that
 * only receive node data. This cache lets them resolve the same translated
 * labels without coupling to Pinia. The workflow store populates it once on
 * bootstrap, before any node is rendered.
 *
 * @since 2.0.0
 */

export interface CatalogConditionOption {
  label: string;
  value: string;
}

export interface CatalogCondition {
  key: string;
  title?: string;
  description?: string;
  operators?: string[];
  value_type?: string;
  requires?: string[];
  options?: CatalogConditionOption[];
}

interface ConditionsCatalog {
  operators?: Record<string, string>;
  triggers?: Record<string, CatalogCondition[]>;
}

let catalog: ConditionsCatalog = {};

/**
 * Populate the module-level catalog cache from the bootstrap payload.
 *
 * @since 2.0.0
 * @param {unknown} value Conditions catalog object from the backend bootstrap.
 * @returns {void}
 */
export function setConditionsCatalog(value: unknown): void {
  catalog = value && typeof value === 'object' ? (value as ConditionsCatalog) : {};
}

/**
 * Resolve the translated label for an operator key, falling back to the key.
 *
 * @since 2.0.0
 * @param {string} operator Operator key.
 * @returns {string} Translated operator label.
 */
export function getConditionOperatorLabel(operator: string): string {
  const key = String(operator || '');
  return catalog.operators?.[key] || key;
}

/**
 * Find a catalog condition by key across all triggers.
 *
 * The same condition key is reused across many triggers with identical
 * metadata, so the first match is enough to resolve its title/options.
 *
 * @since 2.0.0
 * @param {string} key Condition key to look up.
 * @returns {CatalogCondition|null} Matched condition, or null when not found.
 */
export function findCatalogCondition(key: string): CatalogCondition | null {
  const conditionKey = String(key || '');

  if (!conditionKey || !catalog.triggers) {
    return null;
  }

  for (const list of Object.values(catalog.triggers)) {
    if (!Array.isArray(list)) {
      continue;
    }

    const match = list.find((item) => String(item?.key) === conditionKey);

    if (match) {
      return match;
    }
  }

  return null;
}

/**
 * Resolve the translated label for a condition option value, falling back to
 * the raw value.
 *
 * @since 2.0.0
 * @param {CatalogCondition|null} condition Catalog condition holding options.
 * @param {string} value Option value to look up.
 * @returns {string} Translated option label.
 */
export function getConditionOptionLabel(condition: CatalogCondition | null, value: string): string {
  const raw = String(value || '');
  const options = Array.isArray(condition?.options) ? condition!.options! : [];
  const match = options.find((option) => String(option?.value) === raw);

  return match ? String(match.label) : raw;
}
