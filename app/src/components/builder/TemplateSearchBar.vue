<script setup>
/**
 * TemplateSearchBar.vue
 *
 * Thin wrapper around BaseSearchInput that provides a localized "Search
 * templates" placeholder and forwards the input value through v-model for
 * filtering the template library.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
import BaseSearchInput from '../base/BaseSearchInput.vue';

defineProps({
  modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BaseSearchInput
    :model-value="modelValue"
    :placeholder="__('Search templates', textDomain)"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
