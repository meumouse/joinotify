<script setup lang="ts">
/**
 * BaseCodeEditorField.vue
 *
 * Monospaced, dark-themed textarea intended for editing raw code or markup in
 * the builder. Provides a labelled control with v-model support and emits input
 * and change events so parents can react to edits.
 *
 * @since 2.0.0
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  rows: { type: Number, default: 12 },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

/**
 * Handle textarea input, syncing the v-model and emitting the input event.
 *
 * @since 2.0.0
 * @param {Event} event Native input event from the textarea.
 */
function handleInput(event: Event) {
  const target = event.target as HTMLTextAreaElement;
  emit('update:modelValue', target.value);
  emit('input', target.value);
}

/**
 * Handle the textarea change event, forwarding the committed value.
 *
 * @since 2.0.0
 * @param {Event} event Native change event from the textarea.
 */
function handleChange(event: Event) {
  const target = event.target as HTMLTextAreaElement;
  emit('change', target.value);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>
    <textarea
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :disabled="disabled"
      spellcheck="false"
      class="min-h-[220px] w-full rounded-lg border border-slate-200 bg-slate-950 px-4 py-3 font-mono text-sm leading-6 text-slate-100 outline-none transition placeholder:text-slate-500 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-900"
      @input="handleInput"
      @change="handleChange"
    />
  </label>
</template>
