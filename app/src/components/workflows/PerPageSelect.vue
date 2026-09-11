<script setup>
/**
 * PerPageSelect.vue
 *
 * Compact "items per page" picker shared by the paginated admin tables
 * (workflows, message history and processing queue). Emits the chosen page
 * size as a number.
 *
 * @since 2.4.2
 */
import { useId } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { DEFAULT_PER_PAGE, PER_PAGE_OPTIONS } from '../../utils/perPage';
import BaseListboxSelect from '../base/BaseListboxSelect.vue';

defineProps({
  modelValue: { type: Number, default: DEFAULT_PER_PAGE },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const buttonId = useId();
const options = PER_PAGE_OPTIONS.map((size) => ({ label: String(size), value: size }));
</script>

<template>
  <div class="flex items-center gap-2">
    <label :for="buttonId" class="whitespace-nowrap text-[13px] text-slate-500">
      {{ __('Items per page', textDomain) }}
    </label>

    <BaseListboxSelect
      :id="buttonId"
      class="w-[92px]"
      :disabled="disabled"
      :model-value="modelValue"
      :options="options"
      @update:model-value="emit('update:modelValue', Number($event))"
    />
  </div>
</template>
