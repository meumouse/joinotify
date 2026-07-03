<script setup>
/**
 * TemplateCategorySelect.vue
 *
 * Category filter dropdown for the template library. Wraps the shared
 * SelectField with template-specific styling and a localized "All workflows"
 * placeholder, forwarding selection changes through v-model.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import SelectField from '../fields/SelectField.vue';

defineProps({
  modelValue: { type: String, default: 'all' },
  options: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);

/**
 * Base field configuration passed to the underlying SelectField.
 *
 * Provides the localized placeholder and disables search; the actual options
 * are merged in from the `options` prop at the template level.
 *
 * @since 2.0.0
 * @returns {Object} The base SelectField configuration object.
 */
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
