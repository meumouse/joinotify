<script setup>
/**
 * WorkflowStatusSwitch.vue
 *
 * Toggle switch that flips a workflow between draft and publish status. Wraps
 * ToggleSwitch and emits a "change" event with the new status value, showing a
 * spinner while the update is in flight.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import ToggleSwitch from '../toggles/ToggleSwitch.vue';

const props = defineProps({
  modelValue: { type: String, default: 'draft' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['change']);

/**
 * Two-way binding for the toggle: reads the current status from the model
 * value and emits a "change" event with the new status when toggled.
 *
 * @since 2.0.0
 * @returns {import('vue').WritableComputedRef<string>} Writable computed bound to the workflow status.
 */
const statusModel = computed({
  get: () => props.modelValue,
  set: (value) => emit('change', value),
});

</script>

<template>
  <div class="inline-flex items-center gap-2">
    <ToggleSwitch
      v-model="statusModel"
      :aria-label="ariaLabel || __('Toggle workflow status', textDomain)"
      :disabled="disabled || loading"
      false-value="draft"
      size="md"
      true-value="publish"
    />
    <span
      v-if="loading"
      class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary-300 border-r-transparent"
      aria-hidden="true"
    />
  </div>
</template>
