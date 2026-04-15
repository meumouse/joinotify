<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const selectedValues = computed(() => Array.isArray(props.modelValue) ? props.modelValue : []);

function handleChange(event: Event) {
  const target = event.target as HTMLSelectElement;
  const values = Array.from(target.selectedOptions).map((option) => option.value);
  emit('update:modelValue', values);
  emit('change', values);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>
    <select
      multiple
      :disabled="disabled"
      :value="selectedValues"
      class="min-h-[140px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
      @change="handleChange"
    >
      <option v-if="placeholder" disabled value="">
        {{ placeholder }}
      </option>
      <option v-for="option in options" :key="String(option.value)" :value="option.value" :disabled="option.disabled">
        {{ option.label }}
      </option>
    </select>
  </label>
</template>
