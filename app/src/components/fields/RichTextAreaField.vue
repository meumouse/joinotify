<script setup lang="ts">
/**
 * RichTextAreaField.vue
 *
 * Schema-driven field wrapper that adapts a workflow field definition to the
 * BaseRichTextArea component. Maps the field's label, description, placeholder,
 * rows and variable list into props and forwards v-model/input/change events
 * upward.
 *
 * The editor options (`single_line`, `allow_formatting`, `allow_emoji`) are
 * read from the field itself or from its `component_props`, the place the
 * PHP settings helpers put them.
 *
 * @since 2.0.0
 * @version 2.8.0
 */
import { computed } from 'vue';
import BaseRichTextArea from '../base/BaseRichTextArea.vue';

// The field registry passes every component prop as an attribute; only the
// ones mapped below may reach the editor, or `label` and the like would be
// applied twice.
defineOptions({ inheritAttrs: false });

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  showHeader: { type: Boolean, default: true },
});

defineEmits(['update:modelValue', 'input', 'change']);

/**
 * Read an editor option from the field, then from its component props.
 *
 * @since 2.8.0
 * @param {string} key Option key, in snake_case.
 * @param {boolean} fallback Value when the field does not set it.
 * @returns {boolean} The option value.
 */
function option(key: string, fallback: boolean): boolean {
  const componentProps = (props.field?.component_props || {}) as Record<string, unknown>;
  const value = key in (props.field || {}) ? (props.field as Record<string, unknown>)[key] : componentProps[key];

  return value === undefined || value === null ? fallback : Boolean(value);
}

const placeholders = computed(() => (Array.isArray(props.field?.placeholders) ? props.field.placeholders : []));
const singleLine = computed(() => option('single_line', false));
const allowFormatting = computed(() => option('allow_formatting', true));
const allowEmoji = computed(() => option('allow_emoji', true));
</script>

<template>
  <BaseRichTextArea
    :model-value="String(modelValue || '')"
    :id="name"
    :name="name"
    :label="showHeader ? field.label : ''"
    :description="showHeader ? field.description : ''"
    :placeholder="field.placeholder || ''"
    :disabled="field.disabled || false"
    :rows="field.rows || 4"
    :placeholders="placeholders"
    :single-line="singleLine"
    :allow-formatting="allowFormatting"
    :allow-emoji="allowEmoji"
    @update:model-value="$emit('update:modelValue', $event)"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  />
</template>
