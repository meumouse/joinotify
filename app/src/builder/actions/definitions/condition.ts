import ConditionSettings from '../settings/ConditionSettings.vue';
import { describeConditionAction, truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';

function normalizeConditionData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || 'Condition'),
    description: String(data.description || ''),
    action: 'condition',
    condition: String(data.condition || ''),
    condition_type: String(data.condition_type || ''),
    field_id: String(data.field_id || ''),
    meta_key: String(data.meta_key || ''),
    value_text: String(data.value_text || ''),
    type_text: String(data.type_text || ''),
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
