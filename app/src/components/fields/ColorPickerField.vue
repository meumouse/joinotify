<script setup>
/**
 * ColorPickerField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed, ref, watch } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { normalizeHex } from '../../utils/color';

const props = defineProps({
  modelValue: { type: String, default: '#4f46e5' },
  name: { type: String, required: true },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  inputId: { type: String, default: '' },
  placeholder: { type: String, default: '#4f46e5' },
  disabled: { type: Boolean, default: false },
  showHeader: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const picker = ref(null);
const textValue = ref(normalizeHex(props.modelValue) || props.modelValue || '');

const fieldId = computed(() => props.inputId || props.name || `joinotify-color-${Math.random().toString(36).slice(2, 10)}`);
const previewColor = computed(() => normalizeHex(props.modelValue) || '#4f46e5');

watch(
  () => props.modelValue,
  (value) => {
    textValue.value = normalizeHex(value) || value || '';
  }
);

function openPicker() {
  if (props.disabled || !picker.value) {
    return;
  }

  picker.value.click();
}

function commitValue(value) {
  const normalized = normalizeHex(value);

  textValue.value = value;

  if (!normalized) {
    return;
  }

  emit('update:modelValue', normalized);
  textValue.value = normalized;
}

function syncFromText(event) {
  commitValue(event.target.value);
}

function syncFromPicker(event) {
  const normalized = normalizeHex(event.target.value);

  if (!normalized) {
    return;
  }

  emit('update:modelValue', normalized);
  textValue.value = normalized;
}

function resetFromBlur() {
  textValue.value = normalizeHex(props.modelValue) || props.modelValue || '';
}
</script>

<template>
  <div class="space-y-2">
    <div v-if="showHeader" class="space-y-1">
      <label :for="fieldId" class="block text-sm font-semibold leading-none text-slate-700">
        {{ label }}
      </label>

      <p v-if="description" class="text-xs leading-5 text-slate-500">
        {{ description }}
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <button
        :aria-label="label || __('Pick a color', textDomain)"
        :disabled="disabled"
        :style="{ backgroundColor: previewColor }"
        class="relative h-11 w-11 shrink-0 rounded-xl border border-slate-200 shadow-sm transition hover:scale-[1.02] disabled:cursor-not-allowed disabled:opacity-60"
        type="button"
        @click="openPicker"
      >
        <span class="absolute inset-0 rounded-xl ring-1 ring-inset ring-white/25" aria-hidden="true" />
      </button>

      <input
        ref="picker"
        :id="`${fieldId}-picker`"
        :name="`${name}-picker`"
        :value="previewColor"
        class="sr-only"
        type="color"
        @input="syncFromPicker"
        @change="syncFromPicker"
      >

      <input
        :id="fieldId"
        :name="name"
        :value="textValue"
        :placeholder="placeholder"
        :disabled="disabled"
        class="joinotify-otp-login__input w-40 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50"
        inputmode="text"
        type="text"
        @input="syncFromText"
        @blur="resetFromBlur"
      >
    </div>
  </div>
</template>
