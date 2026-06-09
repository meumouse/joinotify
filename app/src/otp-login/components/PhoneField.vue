<script setup>
import { ref } from 'vue';
import Field from './Field.vue';

/**
 * Phone number input wrapper with validation messaging support.
 *
 * @since 1.0.0
 * @type {Object}
 */
defineProps({
  fieldId: {
    type: String,
    required: true,
  },
  bare: {
    type: Boolean,
    default: false,
  },
  helper: {
    type: String,
    default: '',
  },
  validationMessage: {
    type: String,
    default: '',
  },
  showValidation: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    required: true,
  },
});

const modelValue = defineModel({ type: String, default: '' });
const emit = defineEmits(['blur', 'change', 'countrychange', 'input']);

const inputEl = ref(null);

/**
 * Expose the raw input element so intl-tel-input can attach to it.
 *
 * @since 1.0.0
 * @return {void}
 */
defineExpose({ inputEl });
</script>

<template>
  <Field v-if="!bare" :for-id="fieldId" :helper="helper" :label="label">
    <input
      :id="fieldId"
      ref="inputEl"
      v-model="modelValue"
      autocomplete="tel"
      class="joinotify-otp-login__input joinotify-otp-login__phone-input w-full border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400"
      inputmode="tel"
      type="tel"
      @blur="emit('blur', $event)"
      @change="emit('change', $event)"
      @countrychange="emit('countrychange', $event)"
      @input="emit('input', $event)"
    />
  </Field>
  <input
    v-else
    :id="fieldId"
    ref="inputEl"
    v-model="modelValue"
    autocomplete="tel"
    class="joinotify-otp-login__input joinotify-otp-login__phone-input w-full border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400"
    inputmode="tel"
    type="tel"
    @blur="emit('blur', $event)"
    @change="emit('change', $event)"
    @countrychange="emit('countrychange', $event)"
    @input="emit('input', $event)"
  />
  <p
    v-if="showValidation && validationMessage"
    class="mt-2 text-xs leading-5 text-rose-600"
  >
    {{ validationMessage }}
  </p>
</template>
