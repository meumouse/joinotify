<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseCodeEditorField from '../../components/base/BaseCodeEditorField.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue', 'placeholder-selected']);

const conditionOptions = [
  { label: __('User role', textDomain), value: 'user_role' },
  { label: __('Order status', textDomain), value: 'order_status' },
  { label: __('Cart total', textDomain), value: 'cart_total' },
  { label: __('Items in cart', textDomain), value: 'items_in_cart' },
  { label: __('Field value', textDomain), value: 'field_value' },
  { label: __('Meta value', textDomain), value: 'meta_value' },
  { label: __('Post type', textDomain), value: 'post_type' },
  { label: __('Post status', textDomain), value: 'post_status' },
];

const operatorOptions = [
  { label: __('Is', textDomain), value: 'is' },
  { label: __('Is not', textDomain), value: 'is_not' },
  { label: __('Contains', textDomain), value: 'contains' },
  { label: __('Does not contain', textDomain), value: 'not_contain' },
  { label: __('Starts with', textDomain), value: 'start_with' },
  { label: __('Ends with', textDomain), value: 'finish_with' },
  { label: __('Greater than', textDomain), value: 'bigger_than' },
  { label: __('Less than', textDomain), value: 'less_than' },
  { label: __('Empty', textDomain), value: 'empty' },
  { label: __('Not empty', textDomain), value: 'not_empty' },
];

function update(draft: Record<string, unknown>, key: string, value: unknown) {
  return {
    ...draft,
    [key]: value,
  };
}
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      tone="info"
      :title="__('Branching action', textDomain)"
      :message="__('This action creates true and false branches. Each branch can contain its own nested actions.', textDomain)"
    />

    <FieldGroup :title="__('Condition rule', textDomain)" :description="__('Choose the condition family and operator.', textDomain)">
      <BaseTextField
        :model-value="String(modelValue.title || '')"
        :label="__('Title', textDomain)"
        :placeholder="__('Condition', textDomain)"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'title', $event))"
      />
      <BaseSelectField
        :model-value="String(modelValue.condition || '')"
        :options="conditionOptions"
        :label="__('Condition type', textDomain)"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'condition', $event))"
      />
      <BaseSelectField
        :model-value="String(modelValue.condition_type || '')"
        :options="operatorOptions"
        :label="__('Operator', textDomain)"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'condition_type', $event))"
      />
    </FieldGroup>

    <FieldGroup :title="__('Rule data', textDomain)" :description="__('Additional values used by the condition engine.', textDomain)">
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseTextField
          :model-value="String(modelValue.field_id || '')"
          :label="__('Field ID', textDomain)"
          @update:model-value="$emit('update:modelValue', update(modelValue, 'field_id', $event))"
        />
        <BaseTextField
          :model-value="String(modelValue.meta_key || '')"
          :label="__('Meta key', textDomain)"
          @update:model-value="$emit('update:modelValue', update(modelValue, 'meta_key', $event))"
        />
      </div>
      <BaseCodeEditorField
        :model-value="String(modelValue.value_text || '')"
        :label="__('Value', textDomain)"
        :rows="6"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'value_text', $event))"
      />
      <BaseTextField
        :model-value="String(modelValue.type_text || '')"
        :label="__('Type label', textDomain)"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'type_text', $event))"
      />
    </FieldGroup>

    <PlaceholderList
      v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      @select="$emit('placeholder-selected', $event)"
    />
  </div>
</template>
