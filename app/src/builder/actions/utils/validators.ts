/**
 * validators.ts
 *
 * Shared validation helpers for builder action settings. Provides utilities to
 * normalize validation-error maps, produce required-field errors and check
 * whether a validation map is empty.
 *
 * @since 2.0.0
 */
import type { BuilderActionValidationMap } from '../registry/types';
import { __, sprintf, textDomain } from '../../../utils/i18n';

/**
 * Drop empty entries from a validation-error map, keeping only real messages.
 *
 * @since 2.0.0
 * @param {BuilderActionValidationMap} errors Raw validation-error map.
 * @returns {BuilderActionValidationMap} Map with only non-empty messages.
 */
export function normalizeValidationErrors(errors: BuilderActionValidationMap): BuilderActionValidationMap {
  return Object.entries(errors || {}).reduce<BuilderActionValidationMap>((accumulator, [key, value]) => {
    if (String(value || '').trim()) {
      accumulator[key] = String(value);
    }

    return accumulator;
  }, {});
}

/**
 * Build a validation-error map for required fields that are empty.
 *
 * @since 2.0.0
 * @param {Object} data Action data to validate.
 * @param {Array} fields Field descriptors with key, label and optional message.
 * @returns {BuilderActionValidationMap} Map of errors for empty required fields.
 */
export function requiredFieldErrors(
  data: Record<string, unknown>,
  fields: Array<{ key: string; label: string; message?: string }>
): BuilderActionValidationMap {
  return normalizeValidationErrors(
    fields.reduce<BuilderActionValidationMap>((errors, field) => {
      const value = data[field.key];
      const empty = value === undefined || value === null || String(value).trim() === '';

      if (empty) {
        errors[field.key] = field.message || sprintf(__('%s is required.', textDomain), field.label);
      }

      return errors;
    }, {})
  );
}

/**
 * Determine whether a validation map represents a valid (error-free) state.
 *
 * @since 2.0.0
 * @param {BuilderActionValidationMap} errors Validation-error map.
 * @returns {boolean} True when the map contains no errors.
 */
export function isValidValidationMap(errors: BuilderActionValidationMap): boolean {
  return Object.keys(errors || {}).length === 0;
}
