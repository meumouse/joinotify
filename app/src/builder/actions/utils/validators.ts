import type { BuilderActionValidationMap } from '../registry/types';

export function normalizeValidationErrors(errors: BuilderActionValidationMap): BuilderActionValidationMap {
  return Object.entries(errors || {}).reduce<BuilderActionValidationMap>((accumulator, [key, value]) => {
    if (String(value || '').trim()) {
      accumulator[key] = String(value);
    }

    return accumulator;
  }, {});
}

export function requiredFieldErrors(
  data: Record<string, unknown>,
  fields: Array<{ key: string; label: string; message?: string }>
): BuilderActionValidationMap {
  return normalizeValidationErrors(
    fields.reduce<BuilderActionValidationMap>((errors, field) => {
      const value = data[field.key];
      const empty = value === undefined || value === null || String(value).trim() === '';

      if (empty) {
        errors[field.key] = field.message || `${field.label} is required.`;
      }

      return errors;
    }, {})
  );
}

export function isValidValidationMap(errors: BuilderActionValidationMap): boolean {
  return Object.keys(errors || {}).length === 0;
}
