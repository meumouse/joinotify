<script setup>
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
