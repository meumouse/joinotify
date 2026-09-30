<script setup>
/**
 * AttributeInput.vue — the input of a custom field, by its type.
 *
 * Values keep the platform's types: numbers as numbers, yes/no as booleans,
 * multiple choice as a list, date-time as ISO 8601. An empty value is an
 * empty string, which the save route turns into "clear this field".
 *
 * @since 2.5.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: [String, Number, Boolean, Array], default: '' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const inputType = computed(() => ({ email: 'email', url: 'url', phone: 'tel', number: 'number', date: 'date' })[props.field.type] || 'text');

const selectOptions = computed(() => [
  { label: __('— None —', textDomain), value: '' },
  ...(props.field.options || []).map((option) => ({ label: option, value: option })),
]);

const booleanOptions = [
  { label: __('— None —', textDomain), value: '' },
  { label: __('Yes', textDomain), value: 'true' },
  { label: __('No', textDomain), value: 'false' },
];

const booleanValue = computed(() => (props.modelValue === true ? 'true' : props.modelValue === false ? 'false' : ''));

const listValue = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));

// <input type="datetime-local"> speaks local "YYYY-MM-DDTHH:mm"; the platform, ISO 8601.
const localDateTime = computed(() => {
  if (!props.modelValue) {
    return '';
  }

  const date = new Date(String(props.modelValue));

  if (Number.isNaN(date.getTime())) {
    return '';
  }

  const offset = date.getTimezoneOffset() * 60000;

  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
});

function onInput(event) {
  const value = event.target.value;

  if (props.field.type === 'number') {
    emit('update:modelValue', value === '' ? '' : Number(value));
    return;
  }

  emit('update:modelValue', value);
}

function onDateTime(event) {
  const value = event.target.value;

  emit('update:modelValue', value ? new Date(value).toISOString() : '');
}

function onBoolean(value) {
  emit('update:modelValue', value === 'true' ? true : value === 'false' ? false : '');
}

function toggleOption(option, checked) {
  const next = checked ? [...listValue.value, option] : listValue.value.filter((item) => item !== option);

  emit('update:modelValue', next);
}
</script>

<template>
  <div class="flex flex-col gap-1">
    <label class="text-[12px] font-medium text-slate-500">{{ field.label }}</label>

    <BaseListboxSelect
      v-if="field.type === 'select'"
      :disabled="disabled"
      :model-value="modelValue || ''"
      :options="selectOptions"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <BaseListboxSelect
      v-else-if="field.type === 'boolean'"
      :disabled="disabled"
      :model-value="booleanValue"
      :options="booleanOptions"
      @update:model-value="onBoolean"
    />

    <div v-else-if="field.type === 'multi_select'" class="flex flex-wrap gap-x-4 gap-y-2 rounded-[8px] border border-slate-200 px-3 py-2">
      <label v-for="option in field.options || []" :key="option" class="inline-flex items-center gap-2 text-[14px] text-slate-700">
        <input
          type="checkbox"
          :checked="listValue.includes(option)"
          :disabled="disabled"
          @change="toggleOption(option, $event.target.checked)"
        />
        {{ option }}
      </label>
      <span v-if="!(field.options || []).length" class="text-[13px] text-slate-400">{{ __('This field has no options yet.', textDomain) }}</span>
    </div>

    <input
      v-else-if="field.type === 'datetime'"
      type="datetime-local"
      :class="inputClass"
      :disabled="disabled"
      :value="localDateTime"
      @input="onDateTime"
    />

    <input
      v-else
      :type="inputType"
      :class="inputClass"
      :disabled="disabled"
      :value="modelValue"
      @input="onInput"
    />

    <p v-if="error" class="text-[12px] text-danger">{{ error }}</p>
  </div>
</template>
