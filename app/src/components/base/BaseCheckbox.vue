<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  ariaLabel: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputRef = ref(null);
const inputId = computed(() => props.id || `checkbox-${Math.random().toString(36).slice(2, 10)}`);

watch(
  () => props.indeterminate,
  (value) => {
    if (inputRef.value) {
      inputRef.value.indeterminate = Boolean(value) && !props.modelValue;
    }
  },
  { immediate: true }
);

function handleChange(event) {
  const checked = event.target.checked;
  emit('update:modelValue', checked);
  emit('change', checked);
}
</script>

<template>
  <label
    class="inline-flex items-center gap-3"
    :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
    :for="inputId"
  >
    <span class="relative inline-flex h-5 w-5 shrink-0">
      <input
        :id="inputId"
        ref="inputRef"
        :name="name"
        :checked="modelValue"
        :aria-label="ariaLabel || label"
        :disabled="disabled"
        class="peer sr-only"
        type="checkbox"
        @change="handleChange"
      >
      <span
        aria-hidden="true"
        class="absolute inset-0 rounded-md border border-slate-300 bg-white transition peer-focus-visible:ring-4 peer-focus-visible:ring-primary-700/10 peer-checked:border-primary-700 peer-checked:bg-primary-700"
      />
      <svg
        aria-hidden="true"
        viewBox="0 0 20 20"
        class="absolute inset-0 h-5 w-5 scale-0 fill-none stroke-white stroke-[2.5] transition peer-checked:scale-100"
      >
        <path d="M4 10.5L8.1 14.5L16 5.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </span>

    <span v-if="label" class="text-sm font-medium text-slate-700">{{ label }}</span>
  </label>
</template>
