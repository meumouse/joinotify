<script setup lang="ts">
/**
 * BaseTextFieldVariables.vue
 *
 * Single-line text field with a trailing variable picker, for fields that
 * support placeholder substitution (e.g. the WhatsApp recipient). The chosen
 * variable is inserted at the caret position.
 *
 * @since 2.0.0
 */
import { ref } from 'vue';
import VariablePicker from '../../../components/base/VariablePicker.vue';

interface PlaceholderItem {
  placeholder: string;
  description?: string;
}

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
  placeholders: { type: Array as () => Array<PlaceholderItem | string>, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

const inputRef = ref<HTMLInputElement | null>(null);
const selection = ref({ start: 0, end: 0 });

function syncSelection() {
  const input = inputRef.value;

  if (!input) {
    return;
  }

  selection.value = {
    start: input.selectionStart ?? String(props.modelValue || '').length,
    end: input.selectionEnd ?? String(props.modelValue || '').length,
  };
}

function handleInput(event: Event) {
  const value = (event.target as HTMLInputElement).value;
  emit('update:modelValue', value);
  emit('input', value);
  syncSelection();
}

function handleChange(event: Event) {
  emit('change', (event.target as HTMLInputElement).value);
}

function insertVariable(placeholder: string) {
  if (props.disabled || !placeholder) {
    return;
  }

  const value = String(props.modelValue || '');
  const { start, end } = selection.value;
  const nextValue = `${value.slice(0, start)}${placeholder}${value.slice(end)}`;
  const cursor = start + placeholder.length;

  emit('update:modelValue', nextValue);
  emit('input', nextValue);

  void Promise.resolve().then(() => {
    const input = inputRef.value;

    if (input && !props.disabled) {
      input.focus();
      input.setSelectionRange(cursor, cursor);
      selection.value = { start: cursor, end: cursor };
    }
  });
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>

    <div class="flex items-stretch gap-2">
      <input
        ref="inputRef"
        :id="id"
        :name="name"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
        @input="handleInput"
        @change="handleChange"
        @focus="syncSelection"
        @keyup="syncSelection"
        @mouseup="syncSelection"
        @select="syncSelection"
      />

      <VariablePicker
        v-if="Array.isArray(placeholders) && placeholders.length"
        :placeholders="placeholders"
        :disabled="disabled"
        button-class="inline-flex h-[46px] w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
        @select="insertVariable"
      />
    </div>
  </label>
</template>
