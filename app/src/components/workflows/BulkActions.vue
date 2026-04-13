<script setup>
import { computed } from 'vue';
import BaseButton from '../buttons/button/BaseButton.vue';
import SelectField from '../fields/SelectField.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  selectedCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Bulk actions' },
});

defineEmits(['update:modelValue', 'apply']);

const field = computed(() => ({
  label: 'Bulk actions',
  placeholder: props.placeholder || 'Bulk actions',
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
      title="Apply"
      variant="secondary"
      @click="$emit('apply')"
    />
  </div>
</template>
