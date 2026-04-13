<script setup>
import BaseButton from '../buttons/button/BaseButton.vue';
import BaseSelect from '../buttons/select/BaseSelect.vue';

defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  selectedCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Bulk actions' },
});

defineEmits(['update:modelValue', 'apply']);
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
    <BaseSelect
      :model-value="modelValue"
      :options="options"
      :placeholder="placeholder"
      :disabled="disabled"
      label="Bulk actions"
      class="w-full"
      @update:modelValue="$emit('update:modelValue', $event)"
    />

    <BaseButton
      :disabled="disabled || !selectedCount || !modelValue"
      :loading="loading"
      title="Apply"
      variant="secondary"
      @click="$emit('apply')"
    />
  </div>
</template>
