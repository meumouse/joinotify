<script setup>
/**
 * BaseSearchInput.vue
 *
 * Thin wrapper around BaseInput preset as a search field with a localized
 * default placeholder. Forwards its value through v-model for use in list and
 * table filtering.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
import BaseInput from './BaseInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: () => __('Search', textDomain) },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseInput
    :model-value="modelValue"
    :placeholder="placeholder"
    type="search"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
