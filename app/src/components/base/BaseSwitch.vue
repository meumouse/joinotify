<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

function toggle() {
  if (props.disabled) return;
  emit('update:modelValue', !props.modelValue);
  emit('change', !props.modelValue);
}
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-3 rounded-full px-1 py-1 text-left transition"
    :class="disabled ? 'cursor-not-allowed opacity-60' : 'hover:bg-slate-100'"
    :disabled="disabled"
    @click="toggle"
  >
    <span
      class="relative inline-flex h-7 w-12 items-center rounded-full transition"
      :class="modelValue ? 'bg-primary-700' : 'bg-slate-300'"
    >
      <span
        class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
        :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
      />
    </span>
    <span v-if="label" class="text-sm font-medium text-slate-700">{{ label }}</span>
  </button>
</template>
