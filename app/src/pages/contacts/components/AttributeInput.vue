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
import BaseDatePicker from '../../../components/base/BaseDatePicker.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
// The admin's global input styles turn a native checkbox into an empty circle; this one hides it.
import BaseCheckbox from '../../../components/buttons/checkbox/BaseCheckbox.vue';

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: [String, Number, Boolean, Array], default: '' },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const inputType = computed(() => ({ email: 'email', url: 'url', phone: 'tel', number: 'number' })[props.field.type] || 'text');

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

// The date picker and the time input speak local "YYYY-MM-DD" and "HH:mm"; the platform, ISO 8601.
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

const datePart = computed(() => localDateTime.value.slice(0, 10));
const timePart = computed(() => localDateTime.value.slice(11, 16));

function onInput(event) {
  const value = event.target.value;

  if (props.field.type === 'number') {
    emit('update:modelValue', value === '' ? '' : Number(value));
    return;
  }

  emit('update:modelValue', value);
}

/**
 * Join the picked day and time into ISO 8601. A day without a time is midnight;
 * clearing the day clears the value.
 *
 * @since 2.5.0
 * @param {string} day Local `YYYY-MM-DD`.
 * @param {string} time Local `HH:mm`.
 */
function onDateTime(day, time) {
  emit('update:modelValue', day ? new Date(`${day}T${time || '00:00'}`).toISOString() : '');
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
      <BaseCheckbox
        v-for="option in field.options || []"
        :key="option"
        :disabled="disabled"
        :label="option"
        :model-value="listValue.includes(option)"
        @change="toggleOption(option, $event)"
      />
      <span v-if="!(field.options || []).length" class="text-[13px] text-slate-400">{{ __('This field has no options yet.', textDomain) }}</span>
    </div>

    <BaseDatePicker
      v-else-if="field.type === 'date'"
      :disabled="disabled"
      :model-value="typeof modelValue === 'string' ? modelValue.slice(0, 10) : ''"
      @update:model-value="$emit('update:modelValue', $event || '')"
    />

    <div v-else-if="field.type === 'datetime'" class="flex gap-2">
      <div class="flex-1">
        <BaseDatePicker :disabled="disabled" :model-value="datePart" @update:model-value="onDateTime($event, timePart)" />
      </div>
      <input
        type="time"
        class="w-32"
        :class="inputClass"
        :aria-label="__('Time', textDomain)"
        :disabled="disabled || !datePart"
        :value="timePart"
        @input="onDateTime(datePart, $event.target.value)"
      />
    </div>

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
