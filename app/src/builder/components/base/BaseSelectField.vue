<script setup lang="ts">
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';

defineProps({
  modelValue: { type: [String, Number, Boolean], default: '' },
  options: { type: Array, default: () => [] },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'change']);
</script>

<template>
  <BaseListboxSelect
    :model-value="modelValue"
    :options="options"
    :id="id"
    :name="name"
    :label="label"
    :placeholder="placeholder"
    :disabled="disabled"
    @update:model-value="$emit('update:modelValue', $event)"
    @change="$emit('change', $event)"
  />
</template>
