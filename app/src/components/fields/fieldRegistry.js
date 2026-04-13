import ToggleSwitch from '../toggles/ToggleSwitch.vue';
import TextField from './TextField.vue';
import TextAreaField from './TextAreaField.vue';
import SelectField from './SelectField.vue';
import PhoneField from './PhoneField.vue';
import InputGroupField from './InputGroupField.vue';
import InputButtonField from './InputButtonField.vue';
import OtpField from './OtpField.vue';
import ColorPickerField from './ColorPickerField.vue';
import ColorScaleField from './ColorScaleField.vue';

const registry = new Map();

function normalizeName(name) {
  return String(name || '')
    .trim()
    .toLowerCase()
    .replace(/[\s_-]+/g, '');
}

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

function toCamelCase(key) {
  return String(key || '').replace(/[_-](\w)/g, (_, character) => character.toUpperCase());
}

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

export function buildFieldProps(field, overrides = {}) {
  const componentProps = field?.component_props && typeof field.component_props === 'object' ? field.component_props : {};
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
    ...componentProps,
    ...overrides,
  };

  return withCamelCaseAliases(settings);
}

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

export function getRegisteredFieldComponents() {
  return Array.from(registry.entries()).reduce((accumulator, [key, component]) => {
    accumulator[key] = component;
    return accumulator;
  }, {});
}

export function getSupportedFieldTypes() {
  return ['toggle', 'text', 'textarea', 'select', 'phone', 'color', 'color-scale', 'input-group'];
}

export function getSupportedFieldComponents() {
  return ['toggle', 'text', 'textarea', 'select', 'phone', 'input-group', 'input-button', 'otp', 'color-picker', 'color-scale'];
}

syncWindowRegistry();
