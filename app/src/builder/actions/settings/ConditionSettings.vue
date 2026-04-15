<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseCodeEditorField from '../../components/base/BaseCodeEditorField.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue', 'placeholder-selected']);

const conditionOptions = [
  { label: 'User role', value: 'user_role' },
  { label: 'Order status', value: 'order_status' },
  { label: 'Cart total', value: 'cart_total' },
  { label: 'Items in cart', value: 'items_in_cart' },
  { label: 'Field value', value: 'field_value' },
  { label: 'Meta value', value: 'meta_value' },
  { label: 'Post type', value: 'post_type' },
  { label: 'Post status', value: 'post_status' },
];

const operatorOptions = [
  { label: 'Is', value: 'is' },
  { label: 'Is not', value: 'is_not' },
  { label: 'Contains', value: 'contains' },
  { label: 'Does not contain', value: 'not_contain' },
  { label: 'Starts with', value: 'start_with' },
  { label: 'Ends with', value: 'finish_with' },
  { label: 'Greater than', value: 'bigger_than' },
  { label: 'Less than', value: 'less_than' },
  { label: 'Empty', value: 'empty' },
  { label: 'Not empty', value: 'not_empty' },
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
      title="Branching action"
      message="This action creates true and false branches. Each branch can contain its own nested actions."
    />

    <FieldGroup title="Condition rule" description="Choose the condition family and operator.">
      <BaseTextField
        :model-value="String(modelValue.title || '')"
        label="Title"
        placeholder="Condition"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'title', $event))"
      />
      <BaseSelectField
        :model-value="String(modelValue.condition || '')"
        :options="conditionOptions"
        label="Condition type"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'condition', $event))"
      />
      <BaseSelectField
        :model-value="String(modelValue.condition_type || '')"
        :options="operatorOptions"
        label="Operator"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'condition_type', $event))"
      />
    </FieldGroup>

    <FieldGroup title="Rule data" description="Additional values used by the condition engine.">
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseTextField
          :model-value="String(modelValue.field_id || '')"
          label="Field ID"
          @update:model-value="$emit('update:modelValue', update(modelValue, 'field_id', $event))"
        />
        <BaseTextField
          :model-value="String(modelValue.meta_key || '')"
          label="Meta key"
          @update:model-value="$emit('update:modelValue', update(modelValue, 'meta_key', $event))"
        />
      </div>
      <BaseCodeEditorField
        :model-value="String(modelValue.value_text || '')"
        label="Value"
        :rows="6"
        @update:model-value="$emit('update:modelValue', update(modelValue, 'value_text', $event))"
      />
      <BaseTextField
        :model-value="String(modelValue.type_text || '')"
        label="Type label"
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
