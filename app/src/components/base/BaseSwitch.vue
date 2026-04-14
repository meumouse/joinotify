<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

function toggle() {
  if (props.disabled || props.loading) return;
  emit('update:modelValue', !props.modelValue);
  emit('change', !props.modelValue);
}
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-2 rounded-full px-1 py-1 text-left transition"
    :class="disabled || loading ? 'cursor-not-allowed opacity-60' : 'hover:bg-slate-100'"
    :disabled="disabled || loading"
    @click="toggle"
  >
    <span v-if="label" class="text-sm font-medium text-slate-700">{{ label }}</span>
    <span
      class="relative inline-flex h-7 w-12 items-center rounded-full transition"
      :class="modelValue ? 'bg-primary-700' : 'bg-slate-300'"
    >
      <span
        v-if="loading"
        class="absolute inset-0 flex items-center justify-center text-white"
        aria-hidden="true"
      >
        <span class="inline-flex h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-r-transparent" />
      </span>
      <span
        class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
        :class="[modelValue ? 'translate-x-6' : 'translate-x-1', loading ? 'opacity-0' : 'opacity-100']"
      />
    </span>
  </button>
</template>
