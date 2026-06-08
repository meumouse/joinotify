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
import { computed, ref } from 'vue';
import VariablePicker from '../../../components/base/VariablePicker.vue';
import RichTextPreview from '../../../components/base/RichTextPreview.vue';
import { __, textDomain } from '../../../utils/i18n';

interface PlaceholderItem {
  placeholder: string;
  description?: string;
  replacement?: Record<string, unknown>;
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

const hasVariables = computed(() => String(props.modelValue ?? '').includes('{{'));

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

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition focus-within:border-primary-700 focus-within:ring-4 focus-within:ring-primary-700/10">
      <div class="flex items-stretch">
        <input
          ref="inputRef"
          :id="id"
          :name="name"
          :type="type"
          :value="modelValue"
          :placeholder="placeholder"
          :disabled="disabled"
          class="w-full border-0 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-0 disabled:cursor-not-allowed disabled:bg-slate-50"
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
          button-class="inline-flex w-11 shrink-0 items-center justify-center border-0 border-l border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          @select="insertVariable"
        />
      </div>

      <div v-if="hasVariables" class="border-t border-slate-200 bg-slate-50 px-4 py-3">
        <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">
          {{ __('Preview', textDomain) }}
        </p>
        <RichTextPreview :value="String(modelValue ?? '')" :placeholders="placeholders" />
      </div>
    </div>
  </label>
</template>
