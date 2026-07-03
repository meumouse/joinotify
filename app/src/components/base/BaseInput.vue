<script setup>
/**
 * BaseInput.vue
 *
 * Styled single-line text input with an optional label. Supports the standard
 * input types and syncs its value through v-model, also re-emitting native
 * input and change events for callers that need them.
 *
 * @since 2.0.0
 */
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

/**
 * Handle input events, updating the bound model value and re-emitting "input".
 *
 * @since 2.0.0
 * @param {Event} event The native input event.
 */
function handleInput(event) {
  emit('update:modelValue', event.target.value);
  emit('input', event.target.value);
}

/**
 * Handle change events by re-emitting the current value as "change".
 *
 * @since 2.0.0
 * @param {Event} event The native change event.
 */
function handleChange(event) {
  emit('change', event.target.value);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>
    <input
      :id="id"
      :name="name"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
      @input="handleInput"
      @change="handleChange"
    />
  </label>
</template>
