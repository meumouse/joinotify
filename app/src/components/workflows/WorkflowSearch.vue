<script setup>
import BaseButton from '../buttons/button/BaseButton.vue';

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search workflows...' },
  clearLabel: { type: String, default: 'Clear' },
});

defineEmits(['update:modelValue', 'clear']);
</script>

<template>
  <div class="flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-white p-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] sm:flex-row sm:items-center">
    <label class="flex-1">
      <span class="sr-only">{{ placeholder }}</span>
      <input
        :value="modelValue"
        :placeholder="placeholder"
        class="w-full rounded-[8px] border border-slate-200 bg-white px-4 py-2.5 text-sm text-ink outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-50"
        type="search"
        @input="$emit('update:modelValue', $event.target.value)"
      >
    </label>

    <BaseButton
      :disabled="!modelValue"
      :title="clearLabel"
      variant="secondary"
      @click="$emit('clear')"
    />
  </div>
</template>
