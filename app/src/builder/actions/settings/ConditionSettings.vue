<script setup lang="ts">
import { computed } from 'vue';
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import ConditionProductsField from './ConditionProductsField.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { __, textDomain } from '../../../utils/i18n';

interface CatalogCondition {
  key: string;
  title?: string;
  description?: string;
  operators?: string[];
  value_type?: string;
  requires?: string[];
}

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);
const { update } = useActionSettingsUpdate(props, emit);
const store = useWorkflowBuilderStore();

// Operators that compare against no value.
const OPERATORS_WITHOUT_VALUE = ['empty', 'not_empty'];

const model = computed(() => props.modelValue as Record<string, unknown>);

// Conditions catalog exposed by the backend bootstrap (single source of truth).
const catalog = computed(() => {
  const raw = (store.bootstrap as Record<string, unknown> | undefined)?.conditions;
  return raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {};
});

const operatorLabels = computed<Record<string, string>>(() => {
  const ops = catalog.value.operators;
  return ops && typeof ops === 'object' ? (ops as Record<string, string>) : {};
});

// The workflow trigger decides which conditions are available.
const triggerId = computed(() => String(store.triggerNode?.data?.trigger || ''));

const triggerConditions = computed<CatalogCondition[]>(() => {
  const triggers = catalog.value.triggers as Record<string, CatalogCondition[]> | undefined;
  const list = triggers?.[triggerId.value];
  return Array.isArray(list) ? list : [];
});

const conditionOptions = computed(() => [
  { label: __('Select a condition', textDomain), value: '' },
  ...triggerConditions.value.map((item) => ({ label: String(item.title || item.key), value: String(item.key) })),
]);

const selectedCondition = computed<CatalogCondition | null>(() =>
  triggerConditions.value.find((item) => String(item.key) === String(model.value.condition || '')) || null,
);

const operatorOptions = computed(() => {
  const ops = Array.isArray(selectedCondition.value?.operators) ? selectedCondition.value!.operators! : [];
  return [
    { label: __('Select an operator', textDomain), value: '' },
    ...ops.map((op) => ({ label: operatorLabels.value[op] || op, value: op })),
  ];
});

const valueType = computed(() => String(selectedCondition.value?.value_type || 'text'));
const valueOptions = computed(() => {
  const opts = (selectedCondition.value as (CatalogCondition & { options?: Array<{ label: string; value: string }> }) | null)?.options;
  return Array.isArray(opts) ? opts : [];
});
const requires = computed<string[]>(() => (Array.isArray(selectedCondition.value?.requires) ? selectedCondition.value!.requires! : []));
const requiresMetaKey = computed(() => requires.value.includes('meta_key'));
const requiresFieldId = computed(() => requires.value.includes('field_id'));

const currentOperator = computed(() => String(model.value.condition_type || ''));
const showValueInput = computed(() => !!currentOperator.value && !OPERATORS_WITHOUT_VALUE.includes(currentOperator.value));

const currentValue = computed(() => {
  const content = model.value.condition_content && typeof model.value.condition_content === 'object'
    ? (model.value.condition_content as Record<string, unknown>)
    : {};
  return String(model.value.value ?? content.value ?? '');
});

const booleanOptions = [
  { label: __('Yes', textDomain), value: 'true' },
  { label: __('No', textDomain), value: 'false' },
];

const hasTrigger = computed(() => triggerId.value !== '');
const hasConditions = computed(() => triggerConditions.value.length > 0);
const isProductsValue = computed(() => valueType.value === 'products');

// Changing the condition family invalidates the chosen operator and value.
function onConditionChange(value: unknown) {
  emit('update:modelValue', {
    ...model.value,
    condition: value,
    condition_type: '',
    value: '',
    value_text: '',
  });
}

// Write the canonical comparison value (read by the runtime as
// condition_content.value) and keep value_text in sync for legacy/display.
function setValue(value: unknown) {
  emit('update:modelValue', {
    ...model.value,
    value,
    value_text: value,
  });
}
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      tone="info"
      :title="__('Branching action', textDomain)"
      :message="__('This action creates true and false branches. Each branch can contain its own nested actions.', textDomain)"
    />

    <BaseAlert
      v-if="!hasTrigger"
      tone="warning"
      :title="__('Configure the trigger first', textDomain)"
      :message="__('Choose and configure the workflow trigger before adding conditions.', textDomain)"
    />

    <BaseAlert
      v-else-if="!hasConditions"
      tone="info"
      :title="__('No conditions for this trigger', textDomain)"
      :message="__('This trigger does not expose any conditions to evaluate.', textDomain)"
    />

    <template v-else>
      <FieldGroup :title="__('Condition rule', textDomain)" :description="__('Only conditions and operators supported by the engine for this trigger are shown.', textDomain)">
        <BaseTextField
          :model-value="String(model.title || '')"
          :label="__('Title', textDomain)"
          :placeholder="__('Condition', textDomain)"
          @update:model-value="update('title', $event)"
        />
        <BaseSelectField
          :model-value="String(model.condition || '')"
          :options="conditionOptions"
          :label="__('Condition type', textDomain)"
          @update:model-value="onConditionChange($event)"
        />
        <BaseSelectField
          v-if="model.condition"
          :model-value="currentOperator"
          :options="operatorOptions"
          :label="__('Operator', textDomain)"
          @update:model-value="update('condition_type', $event)"
        />
        <p v-if="selectedCondition?.description" class="text-xs text-slate-500">
          {{ selectedCondition.description }}
        </p>
      </FieldGroup>

      <FieldGroup
        v-if="model.condition && (requiresMetaKey || requiresFieldId || showValueInput)"
        :title="__('Rule data', textDomain)"
        :description="__('Values used by the condition engine.', textDomain)"
      >
        <BaseTextField
          v-if="requiresMetaKey"
          :model-value="String(model.meta_key || '')"
          :label="__('Meta key', textDomain)"
          @update:model-value="update('meta_key', $event)"
        />
        <BaseTextField
          v-if="requiresFieldId"
          :model-value="String(model.field_id || '')"
          :label="__('Field ID', textDomain)"
          @update:model-value="update('field_id', $event)"
        />

        <template v-if="showValueInput">
          <ConditionProductsField
            v-if="isProductsValue"
            :model-value="Array.isArray(model.products) ? model.products : []"
            @update:model-value="update('products', $event)"
          />
          <BaseSelectField
            v-else-if="valueOptions.length"
            :model-value="currentValue"
            :options="[{ label: __('Select a value', textDomain), value: '' }, ...valueOptions]"
            :label="__('Value', textDomain)"
            @update:model-value="setValue($event)"
          />
          <BaseSelectField
            v-else-if="valueType === 'boolean'"
            :model-value="currentValue"
            :options="booleanOptions"
            :label="__('Value', textDomain)"
            @update:model-value="setValue($event)"
          />
          <BaseTextField
            v-else
            :model-value="currentValue"
            :type="valueType === 'number' ? 'number' : 'text'"
            :label="__('Value', textDomain)"
            @update:model-value="setValue($event)"
          />
        </template>
      </FieldGroup>

      <PlaceholderList
        v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
        :placeholders="availablePlaceholders"
        @select="$emit('placeholder-selected', $event)"
      />
    </template>
  </div>
</template>
