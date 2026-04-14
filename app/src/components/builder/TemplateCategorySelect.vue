<script setup>
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import SelectField from '../fields/SelectField.vue';

defineProps({
  modelValue: { type: String, default: 'all' },
  options: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);

const field = computed(() => ({
  placeholder: __('All workflows', textDomain),
  searchable: false,
  options: [],
}));
</script>

<template>
  <SelectField
    name="template-category"
    :field="{ ...field, options }"
    :model-value="modelValue"
    :button-class="'h-12 px-4 text-sm text-slate-700'"
    :dropdown-class="'mt-2'"
    :root-class="'w-full'"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
