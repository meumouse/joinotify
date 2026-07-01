<script setup>
/**
 * BaseSelect.vue
 *
 * Styled wrapper around the native <select> element with an optional label and
 * placeholder. Renders options from an array of { label, value, disabled }
 * entries and syncs the selection through v-model.
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
 * Handle a change on the select, updating the bound model value and emitting
 * "change".
 *
 * @since 2.0.0
 * @param {Event} event The native change event.
 */
function handleChange(event) {
  emit('update:modelValue', event.target.value);
  emit('change', event.target.value);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
      {{ label }}
    </span>
    <select
      :id="inputId"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      class="min-w-[14rem] rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
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
