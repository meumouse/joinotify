<script setup>
/**
 * BaseSelect.vue
 *
 * Lightweight wrapper around the native <select> element that adds a label,
 * placeholder, and v-model support. Renders options from an array and emits
 * change events so it can be reused as a simple form control.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputId = computed(() => props.id || `select-${Math.random().toString(36).slice(2, 10)}`);

/**
 * Handle the native select change event and propagate the selected value.
 *
 * @since 2.0.0
 * @param {Event} event Native change event fired by the select element.
 * @returns {void}
 */
function handleChange(event) {
  emit('update:modelValue', event.target.value);
  emit('change', event.target.value);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-shell-500">
      {{ label }}
    </span>
    <select
      :id="inputId"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      class="min-w-[14rem] rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm text-ink outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-50 disabled:cursor-not-allowed disabled:bg-slate-50"
      @change="handleChange"
    >
      <option v-if="placeholder" value="">
        {{ placeholder }}
      </option>
      <option
        v-for="option in options"
        :key="String(option.value)"
        :disabled="option.disabled"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
  </label>
</template>
