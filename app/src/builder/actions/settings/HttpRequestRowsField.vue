<script setup lang="ts">
/**
 * HttpRequestRowsField.vue
 *
 * Editable list of name/value rows used by the "HTTP request" action for its
 * query parameters, headers and form fields. The value of each row accepts
 * placeholders; the name is plain text.
 *
 * @since 2.7.0
 */
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import { __, textDomain } from '../../../utils/i18n';
import type { HttpRequestRow } from '../definitions/httpRequest';
import type { WorkflowPlaceholderItem } from '../../../types/workflowBuilder';

const props = defineProps({
  modelValue: { type: Array as () => HttpRequestRow[], default: () => [] },
  namePlaceholder: { type: String, default: '' },
  valuePlaceholder: { type: String, default: '' },
  addLabel: { type: String, default: '' },
  placeholders: { type: Array as () => Array<WorkflowPlaceholderItem | string>, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

/**
 * Emit the rows with one field of one row changed.
 *
 * @since 2.7.0
 * @param {number} index Row index.
 * @param {'key'|'value'} field Field to change.
 * @param {unknown} value New value.
 */
function updateRow(index: number, field: 'key' | 'value', value: unknown) {
  emit('update:modelValue', props.modelValue.map((row, current) => (current === index ? { ...row, [field]: String(value ?? '') } : row)));
}

/**
 * Emit the rows with an empty one added at the end.
 *
 * @since 2.7.0
 */
function addRow() {
  emit('update:modelValue', [...props.modelValue, { key: '', value: '' }]);
}

/**
 * Emit the rows without the one at the index.
 *
 * @since 2.7.0
 * @param {number} index Row index.
 */
function removeRow(index: number) {
  emit('update:modelValue', props.modelValue.filter((_row, current) => current !== index));
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="(row, index) in modelValue"
      :key="index"
      class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] sm:items-end"
    >
      <BaseTextField
        :model-value="row.key"
        :label="__('Name', textDomain)"
        :placeholder="namePlaceholder"
        @update:model-value="updateRow(index, 'key', $event)"
      />
      <BaseTextFieldVariables
        :model-value="row.value"
        :label="__('Value', textDomain)"
        :placeholder="valuePlaceholder"
        :placeholders="placeholders"
        @update:model-value="updateRow(index, 'value', $event)"
      />
      <BaseButton
        :title="__('Remove', textDomain)"
        variant="ghost"
        size="sm"
        @click="removeRow(index)"
      />
    </div>

    <BaseButton :title="addLabel || __('Add', textDomain)" variant="secondary" size="sm" @click="addRow" />
  </div>
</template>
