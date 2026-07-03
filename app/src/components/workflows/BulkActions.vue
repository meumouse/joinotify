<script setup>
/**
 * BulkActions.vue
 *
 * Renders the bulk-action selector and apply button for the workflows list.
 * Emits the chosen action via v-model and an "apply" event so the parent can
 * run an action against every selected workflow at once.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';
import SelectField from '../fields/SelectField.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  selectedCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: () => __('Bulk actions', textDomain) },
});

defineEmits(['update:modelValue', 'apply']);

/**
 * Builds the field descriptor consumed by SelectField, normalizing the
 * incoming options and resolving the placeholder/label text.
 *
 * @since 2.0.0
 * @returns {{ label: string, placeholder: string, options: Array, searchable: boolean }} Field configuration for SelectField.
 */
const field = computed(() => ({
  label: __('Bulk actions', textDomain),
  placeholder: props.placeholder || __('Bulk actions', textDomain),
  options: Array.isArray(props.options) ? props.options : [],
  searchable: false,
}));
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
    <SelectField
      :field="field"
      :model-value="modelValue"
      name="bulk_action"
      :disabled="disabled"
      class="w-full"
      @update:modelValue="$emit('update:modelValue', $event)"
    />

    <BaseButton
      :disabled="disabled || !selectedCount || !modelValue"
      :loading="loading"
      :title="__('Apply', textDomain)"
      variant="secondary"
      @click="$emit('apply')"
    />
  </div>
</template>
