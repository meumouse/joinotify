<script setup lang="ts">
/**
 * BaseSwitchField.vue
 *
 * Thin wrapper around the shared BaseSwitch that adapts the toggle control for
 * builder settings, forwarding label, disabled and loading props and relaying
 * v-model and change events.
 *
 * @since 2.0.0
 */
import BaseSwitch from '../../../components/base/BaseSwitch.vue';

defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'change']);
</script>

<template>
  <BaseSwitch
    :model-value="modelValue"
    :label="label"
    :disabled="disabled"
    :loading="loading"
    @update:model-value="$emit('update:modelValue', $event)"
    @change="$emit('change', $event)"
  />
</template>
