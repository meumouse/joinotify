<script setup lang="ts">
/**
 * BaseSelectField.vue
 *
 * Thin wrapper around the shared BaseListboxSelect that adapts the styled
 * dropdown for builder settings, forwarding options, label and placeholder props
 * and relaying v-model and change events.
 *
 * @since 2.0.0
 */
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
