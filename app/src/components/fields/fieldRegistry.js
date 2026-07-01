/**
 * fieldRegistry.js
 *
 * Central registry that maps field type/component names to their Vue field
 * components. Provides normalization and alias helpers, prop-building for
 * schema-driven fields, and a resolver used to look up the right component for
 * a given field definition. Also exposes the registry on window for external
 * registration.
 *
 * @since 2.0.0
 */
import ToggleSwitch from '../toggles/ToggleSwitch.vue';
import TextField from './TextField.vue';
import TextAreaField from './TextAreaField.vue';
import RichTextAreaField from './RichTextAreaField.vue';
import SelectField from './SelectField.vue';
import PhoneField from './PhoneField.vue';
import InputGroupField from './InputGroupField.vue';
import InputButtonField from './InputButtonField.vue';
import OtpField from './OtpField.vue';
import ColorPickerField from './ColorPickerField.vue';
import ColorScaleField from './ColorScaleField.vue';
import OpenAiModelField from './OpenAiModelField.vue';

const registry = new Map();

/**
 * Normalize a field name into a lookup key (trimmed, lowercased, separators removed).
 *
 * @since 2.0.0
 * @param {string} name Raw field type or component name.
 * @returns {string} Normalized registry key.
 */
function normalizeName(name) {
  return String(name || '')
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '');
}

/**
 * Register a component under a normalized alias in the local registry.
 *
 * @since 2.0.0
 * @param {string} name Alias name to register.
 * @param {object} component Vue component to associate with the alias.
 * @returns {void}
 */
function registerAlias(name, component) {
  const key = normalizeName(name);

  if (!key || !component) {
    return;
  }

  registry.set(key, component);
}

registerAlias('toggle', ToggleSwitch);
registerAlias('text', TextField);
registerAlias('textarea', TextAreaField);
registerAlias('richtext', RichTextAreaField);
registerAlias('rich-text', RichTextAreaField);
registerAlias('rich-text-area', RichTextAreaField);
registerAlias('select', SelectField);
registerAlias('phone', PhoneField);
registerAlias('input-group', InputGroupField);
registerAlias('input-group-field', InputGroupField);
registerAlias('input-button', InputButtonField);
registerAlias('input-button-field', InputButtonField);
registerAlias('otp', OtpField);
registerAlias('otp-field', OtpField);
registerAlias('color', ColorPickerField);
registerAlias('color-picker', ColorPickerField);
registerAlias('color-picker-field', ColorPickerField);
registerAlias('color-scale', ColorScaleField);
registerAlias('color-scale-field', ColorScaleField);
registerAlias('openai-model-select', OpenAiModelField);

/**
 * Convert a snake_case or kebab-case key into camelCase.
 *
 * @since 2.0.0
 * @param {string} key Key to transform.
 * @returns {string} camelCase version of the key.
 */
function toCamelCase(key) {
  return String(key || '').replace(/[_-](\w)/g, (_, character) => character.toUpperCase());
}

/**
 * Return a copy of an object with additional camelCase aliases for any
 * snake_case or kebab-case keys, without overriding existing keys.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} value Source object of settings.
 * @returns {Record<string, unknown>} Object with camelCase aliases added.
 */
function withCamelCaseAliases(value) {
  return Object.entries(value).reduce((accumulator, [key, entry]) => {
    accumulator[key] = entry;

    const camelKey = toCamelCase(key);

    if (camelKey && camelKey !== key && !(camelKey in accumulator)) {
      accumulator[camelKey] = entry;
    }

    return accumulator;
  }, {});
}

/**
 * Build the resolved prop set for a schema-driven field, merging defaults,
 * component_props, input-group items, and explicit overrides, then adding
 * camelCase aliases.
 *
 * @since 2.0.0
 * @param {object} field Field definition from the schema.
 * @param {Record<string, unknown>} [overrides] Values that override the derived settings.
 * @returns {Record<string, unknown>} Props object ready to bind to the field component.
 */
export function buildFieldProps(field, overrides = {}) {
  const componentProps = field?.component_props && typeof field.component_props === 'object' ? field.component_props : {};
  const inputGroupItems = Array.isArray(field?.items) ? field.items : [];
  const settings = {
    label: field?.label || '',
    description: field?.description || '',
    placeholder: field?.placeholder || '',
    options: Array.isArray(field?.options) ? field.options : [],
    rows: field?.rows || 4,
    showHeader: false,
    disabled: Boolean(field?.disabled),
    required: Boolean(field?.required),
    wrapperClass: field?.wrapper_class || field?.wrapperClass || '',
    groupClass: field?.group_class || field?.groupClass || '',
    inputClass: field?.input_class || field?.inputClass || '',
    addonClass: field?.addon_class || field?.addonClass || '',
    autocomplete: field?.autocomplete || 'off',
    inputmode: field?.inputmode || field?.inputMode || '',
    searchable: Boolean(field?.searchable),
    searchPlaceholder: field?.search_placeholder || field?.searchPlaceholder || '',
    emptyLabel: field?.empty_label || field?.emptyLabel || '',
    prependText: field?.prepend_text || field?.prependText || '',
    appendText: field?.append_text || field?.appendText || '',
    ...(inputGroupItems.length ? { items: inputGroupItems } : {}),
    ...componentProps,
    ...overrides,
  };

  return withCamelCaseAliases(settings);
}

/**
 * Expose the registry API and current components on the global window object so
 * external code can register and resolve field components.
 *
 * @since 2.0.0
 * @returns {object|null} The global registry object, or null when window is unavailable.
 */
function syncWindowRegistry() {
  if (typeof window === 'undefined') {
    return null;
  }

  window.JoinotifyFieldComponents = window.JoinotifyFieldComponents || {};
  window.JoinotifyFieldComponents.register = registerFieldComponent;
  window.JoinotifyFieldComponents.resolve = resolveFieldComponent;
  window.JoinotifyFieldComponents.list = getRegisteredFieldComponents;
  window.JoinotifyFieldComponents.components = getRegisteredFieldComponents();

  return window.JoinotifyFieldComponents;
}

/**
 * Register a field component by name in both the local and global registries.
 *
 * @since 2.0.0
 * @param {string} name Field type or component name.
 * @param {object} component Vue component to register.
 * @returns {void}
 */
export function registerFieldComponent(name, component) {
  const key = normalizeName(name);

  if (!key || !component) {
    return;
  }

  registry.set(key, component);

  const globalRegistry = syncWindowRegistry();

  if (globalRegistry) {
    globalRegistry[key] = component;
    globalRegistry.components = getRegisteredFieldComponents();
  }
}

/**
 * Resolve the field component for a field definition, checking its explicit
 * component name first and then its type.
 *
 * @since 2.0.0
 * @param {object} field Field definition with optional component and type.
 * @returns {object|null} The matching Vue component, or null when none is found.
 */
export function resolveFieldComponent(field) {
  if (!field || typeof field !== 'object') {
    return null;
  }

  const names = [];

  if (field.component) {
    names.push(field.component);
  }

  if (field.type) {
    names.push(field.type);
  }

  for (const name of names) {
    const component = registry.get(normalizeName(name));

    if (component) {
      return component;
    }
  }

  return null;
}

/**
 * Get a plain object snapshot of all registered field components keyed by alias.
 *
 * @since 2.0.0
 * @returns {Record<string, object>} Map of registry keys to components.
 */
export function getRegisteredFieldComponents() {
  return Array.from(registry.entries()).reduce((accumulator, [key, component]) => {
    accumulator[key] = component;
    return accumulator;
  }, {});
}

/**
 * List the field type identifiers supported by the registry.
 *
 * @since 2.0.0
 * @returns {string[]} Supported field type names.
 */
export function getSupportedFieldTypes() {
  return ['toggle', 'text', 'textarea', 'richtext', 'select', 'phone', 'color', 'color-scale', 'input-group'];
}

/**
 * List the field component identifiers supported by the registry.
 *
 * @since 2.0.0
 * @returns {string[]} Supported field component names.
 */
export function getSupportedFieldComponents() {
  return ['toggle', 'text', 'textarea', 'richtext', 'select', 'phone', 'input-group', 'input-button', 'otp', 'color-picker', 'color-scale'];
}

syncWindowRegistry();
