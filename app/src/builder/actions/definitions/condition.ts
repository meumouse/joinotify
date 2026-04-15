import ConditionSettings from '../settings/ConditionSettings.vue';
import { describeConditionAction, truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';

function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

function normalizeConditionContent(data: Record<string, unknown>): Record<string, unknown> {
  const legacy = isRecord(data.condition_content) ? data.condition_content : {};
  const condition = String(data.condition || legacy.condition || '');
  const conditionType = String(data.condition_type || legacy.type || '');
  const typeText = String(data.type_text || legacy.type_text || '');
  const valueText = String(data.value_text || legacy.value_text || '');
  const value = data.value ?? legacy.value ?? '';

  const content: Record<string, unknown> = {
    condition,
    type: conditionType,
    type_text: typeText,
    value,
    value_text: valueText,
    meta_key: String(data.meta_key || legacy.meta_key || ''),
    field_id: String(data.field_id || legacy.field_id || ''),
  };

  if (Array.isArray(data.products)) {
    content.products = data.products;
  } else if (Array.isArray(legacy.products)) {
    content.products = legacy.products;
  }

  return content;
}

function normalizeConditionData(data: Record<string, unknown>): Record<string, unknown> {
  const conditionContent = normalizeConditionContent(data);

  return {
    title: String(data.title || 'Condition'),
    description: String(data.description || ''),
    action: 'condition',
    condition: String(conditionContent.condition || ''),
    condition_type: String(conditionContent.type || ''),
    field_id: String(conditionContent.field_id || ''),
    meta_key: String(conditionContent.meta_key || ''),
    value_text: String(conditionContent.value_text || ''),
    type_text: String(conditionContent.type_text || ''),
    condition_content: conditionContent,
    settings: data.settings && typeof data.settings === 'object' ? data.settings : {},
  };
}

export const conditionDefinition: ActionDefinition = {
  action: 'condition',
  title: 'Condition',
  description: 'Split the workflow into true and false branches.',
  icon: 'git-branch',
  hasSettings: true,
  priority: 100,
  isExpansible: true,
  defaultData: normalizeConditionData({}),
  settingsComponent: ConditionSettings,
  branchKeys: ['action_true', 'action_false'],
  branchLabels: {
    action_true: 'True branch',
    action_false: 'False branch',
  },
  settingsSchema: [
    { key: 'condition', label: 'Condition type', component: 'select', required: true },
    { key: 'condition_type', label: 'Operator', component: 'select', required: true },
    { key: 'field_id', label: 'Field ID', component: 'input' },
    { key: 'meta_key', label: 'Meta key', component: 'input' },
    { key: 'value_text', label: 'Value', component: 'textarea' },
    { key: 'type_text', label: 'Type label', component: 'input' },
  ],
  normalizeData: normalizeConditionData,
  serializeData: normalizeConditionData,
  buildDescription: (data) => truncateDescription(describeConditionAction(data)),
  validate: (data) => normalizeValidationErrors(requiredFieldErrors(data, [
    { key: 'condition', label: 'Condition type' },
    { key: 'condition_type', label: 'Operator' },
  ])),
};
