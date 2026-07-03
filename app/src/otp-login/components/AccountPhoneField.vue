<script setup>
import { onMounted, ref } from 'vue';
import intlTelInput from 'intl-tel-input';
import ptI18n from 'intl-tel-input/build/js/i18n/pt/index.js';
import 'intl-tel-input/build/css/intlTelInput.css';
import Field from './Field.vue';

/**
 * Phone field used exclusively on the WooCommerce account profile page.
 *
 * @since 1.0.0
 * @type {Object}
 */
const props = defineProps({
  defaultCountry: {
    type: String,
    default: 'br',
  },
  fieldId: {
    type: String,
    default: 'account_phone',
  },
  helper: {
    type: String,
    default: '',
  },
  initialPhone: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: 'Phone number',
  },
});

const modelValue = defineModel({ type: String, default: '' });
const inputEl = ref(null);
const iti = ref(null);

function getIntlTelInputLocale() {
  const locale = String(window.joinotifyOtpLogin?.siteLocale || 'en').toLowerCase();

  if (locale.startsWith('pt')) {
    return {
      countryNameLocale: 'pt',
      i18n: ptI18n,
    };
  }

  return {
    countryNameLocale: locale.split('-')[0] || 'en',
    i18n: {},
  };
}

function loadIntlUtils() {
  return import('intl-tel-input/utils');
}

/**
 * Keep the model value normalized in international format.
 *
 * @since 1.0.0
 * @return {void}
 */
function syncValue() {
  if (iti.value && typeof iti.value.getNumber === 'function') {
    modelValue.value = iti.value.getNumber() || '';
    return;
  }

  const digits = String(inputEl.value?.value || '').replace(/\D+/g, '');
  modelValue.value = digits ? `+${digits}` : '';
}

function handleInput() {
  syncValue();
}

/**
 * Initialize the international phone widget and preload the saved value.
 *
 * @since 1.0.0
 * @return {void}
 */
onMounted(() => {
  const input = inputEl.value;

  if (!input) {
    return;
  }

  iti.value = intlTelInput(input, {
    initialCountry: props.defaultCountry || 'br',
    nationalMode: false,
    formatOnDisplay: true,
    autoPlaceholder: 'aggressive',
    placeholderNumberType: 'MOBILE',
    separateDialCode: false,
    containerClass: 'w-full',
    ...getIntlTelInputLocale(),
    loadUtils: loadIntlUtils,
  });

  if (props.initialPhone && typeof iti.value.setNumber === 'function') {
    iti.value.setNumber(props.initialPhone);
  }

  syncValue();
});
</script>

<template>
  <div class="joinotify-otp-login">
    <Field :for-id="fieldId" :helper="helper" :label="label">
      <input
        :id="fieldId"
        ref="inputEl"
        :value="modelValue"
        autocomplete="tel"
        class="joinotify-otp-login__input joinotify-otp-login__phone-input w-full border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400"
        inputmode="tel"
        name="account_phone"
        type="tel"
        @blur="syncValue"
        @change="syncValue"
        @countrychange="syncValue"
        @input="handleInput"
      />
    </Field>
  </div>
</template>
