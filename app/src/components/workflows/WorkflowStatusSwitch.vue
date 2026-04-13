<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: 'draft' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
});

const emit = defineEmits(['change']);

const checked = computed(() => props.modelValue === 'publish');

function handleChange(event) {
  emit('change', event.target.checked ? 'publish' : 'draft');
}
</script>

<template>
  <label
    class="relative inline-flex items-center gap-3"
    :class="disabled || loading ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
  >
    <input
      :aria-label="ariaLabel || 'Toggle workflow status'"
      :checked="checked"
      :disabled="disabled || loading"
      class="peer sr-only"
      type="checkbox"
      @change="handleChange"
    >
    <span
      aria-hidden="true"
      class="relative inline-flex h-6 w-11 rounded-full border border-slate-200 bg-shell-200 transition peer-focus-visible:ring-4 peer-focus-visible:ring-primary-50 peer-checked:border-primary-700 peer-checked:bg-primary-700"
    >
      <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5" />
    </span>
    <span class="text-sm font-medium" :class="checked ? 'text-primary-800' : 'text-shell-500'">
      {{ checked ? 'Active' : 'Inactive' }}
    </span>
    <span
      v-if="loading"
      class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary-300 border-r-transparent"
      aria-hidden="true"
    />
  </label>
</template>
