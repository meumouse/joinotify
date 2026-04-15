<script setup lang="ts">
import BaseTextarea from '../../../components/base/BaseTextarea.vue';

defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  rows: { type: Number, default: 6 },
});

defineEmits(['update:modelValue', 'input', 'change']);
</script>

<template>
  <BaseTextarea
    :model-value="modelValue"
    :id="id"
    :name="name"
    :label="label"
    :placeholder="placeholder"
    :disabled="disabled"
    :rows="rows"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
