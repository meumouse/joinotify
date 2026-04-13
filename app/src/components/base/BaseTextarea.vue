<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  rows: { type: Number, default: 6 },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

function handleInput(event) {
  emit('update:modelValue', event.target.value);
  emit('input', event.target.value);
}

function handleChange(event) {
  emit('change', event.target.value);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>
    <textarea
      :id="id"
      :name="name"
      :rows="rows"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
      @input="handleInput"
      @change="handleChange"
    />
  </label>
</template>
