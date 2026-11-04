<script setup lang="ts">
/**
 * BaseJsonEditorField.vue
 *
 * JSON template editor for the builder, on CodeMirror: line numbers, syntax
 * highlighting, folding, bracket closing, a lint gutter, {{ placeholder }}
 * highlighting and autocomplete (type "{{"), a "Format" button and the
 * variable picker. CodeMirror is loaded when the field mounts; until then, or
 * if it cannot load, a plain textarea edits the same value.
 *
 * @since 2.7.1
 */
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import type { PropType } from 'vue';
import BaseCodeEditorField from './BaseCodeEditorField.vue';
import VariablePicker from '../../../components/base/VariablePicker.vue';
import { __, textDomain } from '../../../utils/i18n';
import type { JsonEditor, JsonEditorPlaceholder, JsonEditorProblem } from './codemirror/jsonEditor';

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  placeholders: { type: Array as () => Array<JsonEditorPlaceholder | string>, default: () => [] },
  // Problems to show in the gutter for a text, such as a syntax error.
  lint: { type: Function as unknown as PropType<((text: string) => JsonEditorProblem[]) | null>, default: null },
  // Formatted text, or null when it cannot be formatted. No "Format" button without it.
  format: { type: Function as unknown as PropType<((text: string) => string | null) | null>, default: null },
});

const emit = defineEmits(['update:modelValue']);

const autocompleteHint = __('Type {{ to list the variables, or press Ctrl+Space for suggestions.', textDomain);

const host = ref<HTMLElement | null>(null);
const editor = shallowRef<JsonEditor | null>(null);
const failed = ref(false);
const formatFailed = ref(false);

onMounted(async () => {
  try {
    const { createJsonEditor } = await import('./codemirror/jsonEditor');

    if (!host.value) {
      return;
    }

    editor.value = createJsonEditor(host.value, {
      value: props.modelValue,
      label: props.label,
      placeholder: props.placeholder,
      disabled: props.disabled,
      placeholders: props.placeholders,
      lint: props.lint || undefined,
      onChange: (value) => {
        formatFailed.value = false;
        emit('update:modelValue', value);
      },
    });
  } catch {
    failed.value = true;
  }
});

onBeforeUnmount(() => editor.value?.destroy());

watch(() => props.modelValue, (value) => editor.value?.setValue(String(value ?? '')));
watch(() => props.placeholders, (value) => editor.value?.setPlaceholders(value || []));
watch(() => props.disabled, (value) => editor.value?.setDisabled(Boolean(value)));

/**
 * Format the JSON in place, or say it cannot be while it has a syntax error.
 *
 * @since 2.7.1
 */
function formatValue() {
  const formatted = props.format ? props.format(props.modelValue) : null;

  formatFailed.value = formatted === null;

  if (formatted !== null && formatted !== props.modelValue) {
    editor.value?.setValue(formatted);
    emit('update:modelValue', formatted);
  }
}

/**
 * Insert a variable picked from the list at the cursor.
 *
 * @since 2.7.1
 * @param {string} token Placeholder token.
 */
function insertVariable(token: string) {
  if (editor.value) {
    editor.value.insert(token);
  } else {
    emit('update:modelValue', `${props.modelValue}${token}`);
  }
}
</script>

<template>
  <BaseCodeEditorField
    v-if="failed"
    :model-value="modelValue"
    :label="label"
    :placeholder="placeholder"
    :disabled="disabled"
    :rows="8"
    @update:model-value="emit('update:modelValue', $event)"
  />

  <div v-else class="flex flex-col gap-1.5">
    <div class="flex items-center justify-between gap-2">
      <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>
      <div class="ml-auto flex items-center gap-1">
        <button
          v-if="format"
          type="button"
          class="inline-flex h-8 items-center rounded-md px-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="disabled || !modelValue.trim()"
          :title="__('Indent the JSON (it must be valid)', textDomain)"
          @click="formatValue"
        >
          {{ __('Format', textDomain) }}
        </button>
        <VariablePicker
          v-if="placeholders.length"
          :placeholders="placeholders"
          :disabled="disabled"
          @select="insertVariable"
        />
      </div>
    </div>

    <div ref="host" class="min-h-[220px] overflow-hidden rounded-lg bg-[#282c34]" />

    <p v-if="formatFailed" class="text-xs text-red-700">
      {{ __('Fix the JSON before formatting it.', textDomain) }}
    </p>
    <p v-if="placeholders.length" class="text-xs text-slate-500">{{ autocompleteHint }}</p>
  </div>
</template>
