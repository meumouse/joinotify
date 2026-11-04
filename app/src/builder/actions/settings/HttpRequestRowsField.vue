<script setup lang="ts">
/**
 * HttpRequestRowsField.vue
 *
 * Editable list of name/value rows used by the "HTTP request" action for its
 * query parameters, headers, form fields and JSON body fields. The value of
 * each row accepts placeholders; the name is plain text. Given type options
 * (the JSON body fields), each row also picks how its value is sent.
 *
 * @since 2.7.0
 * @version 2.7.1
 */
import { computed } from 'vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import { __, textDomain } from '../../../utils/i18n';
import type { HttpRequestRow } from '../definitions/httpRequest';
import type { WorkflowFieldOption, WorkflowPlaceholderItem } from '../../../types/workflowBuilder';

const props = defineProps({
  modelValue: { type: Array as () => HttpRequestRow[], default: () => [] },
  namePlaceholder: { type: String, default: '' },
  valuePlaceholder: { type: String, default: '' },
  addLabel: { type: String, default: '' },
  placeholders: { type: Array as () => Array<WorkflowPlaceholderItem | string>, default: () => [] },
  typeOptions: { type: Array as () => WorkflowFieldOption[], default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const hasTypes = computed(() => props.typeOptions.length > 0);

/**
 * Emit the rows with one field of one row changed.
 *
 * @since 2.7.0
 * @version 2.7.1
 * @param {number} index Row index.
 * @param {'key'|'value'|'type'} field Field to change.
 * @param {unknown} value New value.
 */
function updateRow(index: number, field: 'key' | 'value' | 'type', value: unknown) {
  emit('update:modelValue', props.modelValue.map((row, current) => (current === index ? { ...row, [field]: String(value ?? '') } : row)));
}

/**
 * Emit the rows with an empty one added at the end.
 *
 * @since 2.7.0
 * @version 2.7.1
 */
function addRow() {
  emit('update:modelValue', [...props.modelValue, hasTypes.value ? { key: '', value: '', type: 'text' } : { key: '', value: '' }]);
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
      :class="[
        'grid grid-cols-1 gap-2 sm:items-end',
        hasTypes ? 'sm:grid-cols-[minmax(0,2fr)_8rem_minmax(0,3fr)_auto]' : 'sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto]',
      ]"
    >
      <BaseTextField
        :model-value="row.key"
        :label="__('Name', textDomain)"
        :placeholder="namePlaceholder"
        @update:model-value="updateRow(index, 'key', $event)"
      />
      <BaseSelectField
        v-if="hasTypes"
        :model-value="row.type || 'text'"
        :options="typeOptions"
        :label="__('Type', textDomain)"
        @update:model-value="updateRow(index, 'type', $event)"
      />
      <BaseTextFieldVariables
        v-if="row.type !== 'null'"
        :model-value="row.value"
        :label="__('Value', textDomain)"
        :placeholder="valuePlaceholder"
        :placeholders="placeholders"
        @update:model-value="updateRow(index, 'value', $event)"
      />
      <span v-else class="pb-2.5 font-mono text-sm text-slate-500">null</span>
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
