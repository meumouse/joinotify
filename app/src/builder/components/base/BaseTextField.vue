<script setup lang="ts">
import BaseInput from '../../../components/base/BaseInput.vue';

defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'input', 'change']);
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    :id="id"
    :name="name"
    :label="label"
    :placeholder="placeholder"
    :type="type"
    :disabled="disabled"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
