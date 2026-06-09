<script setup>
import intlTelInput from 'intl-tel-input';
import ptI18n from 'intl-tel-input/build/js/i18n/pt/index.js';
import 'intl-tel-input/build/css/intlTelInput.css';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import BaseButton from './BaseButton.vue';
import Field from './Field.vue';
import PhoneField from './PhoneField.vue';
import Loader from './Loader.vue';
import FormCheckbox from './FormCheckbox.vue';

/**
 * Root login widget that orchestrates phone, OTP, and password flows.
 *
 * @since 1.0.0
 * @type {Object}
 */
const props = defineProps({
  context: { type: String, default: 'myaccount' },
  defaultCountry: { type: String, default: 'br' },
  apiBaseUrl: { type: String, default: '' },
  description: { type: String, default: '' },
  showHeader: { type: Boolean, default: true },
  otpLength: { type: Number, default: 6 },
  redirectUrl: { type: String, default: '/' },
  title: { type: String, default: '' },
  strings: { type: Object, default: () => ({}) },
  utilsUrl: { type: String, default: '' },
});

const steps = { phone: 'phone', otp: 'otp', password: 'password' };
const __ = window.wp?.i18n?.__ ?? ((text) => text);

const scope = ref(null);
const phoneField = ref(null);
const otpInputs = ref([]);
const otpPhone = ref('');
const visiblePhone = ref('');
const hiddenPhone = ref('');
const currentStep = ref(steps.phone);
const loading = ref(false);
const countdown = ref(60);
const resendEnabled = ref(false);
const resendTimer = ref(null);
const notice = ref({ type: 'info', message: '' });
const remember = ref(false);
const identifier = ref('');
const password = ref('');
const showPassword = ref(false);
const otpDigits = ref(Array.from({ length: props.otpLength }, () => ''));
const phoneIti = ref(null);
const phoneValidationMessage = ref('');
const showPhoneValidation = ref(false);

const apiBaseUrl = computed(() => props.apiBaseUrl || window.joinotifyOtpLogin.restUrl || '');
const useRestApi = computed(() => Boolean(window.joinotifyOtpLogin.restUrl));
const lostPasswordUrl = computed(() => window.joinotifyOtpLogin?.lostPasswordUrl || '#');
const theme = computed(() => window.joinotifyOtpLogin?.theme || {});
const primaryColor = computed(() => theme.value.primaryColor || '#4f46e5');
const borderRadius = computed(() => {
  const value = String(theme.value.borderRadius || '0.375rem').trim();

  if (/^-?\d+(\.\d+)?$/.test(value)) {
    return `${value}px`;
  }

  return value || '0.375rem';
});

/**
 * Resolve a translated string from the WordPress i18n helper.
 *
 * @since 1.0.0
 * @param {string} text Translation string.
 * @param {string} domain Translation domain.
 * @return {string} Localized string.
 */
function t(text, domain = 'joinotify') {
  return props.strings?.[text] || window.joinotifyOtpLogin?.i18n?.[text] || __(text, domain);
}

/**
 * Resolve a translated string containing a single numeric placeholder.
 *
 * @since 1.0.0
 * @param {string} text Translation string.
 * @param {number} value Numeric value to inject.
 * @param {string} domain Translation domain.
 * @return {string} Localized string with the count applied.
 */
function tWithCount(text, value, domain = 'joinotify') {
  return String(t(text, domain)).replace('%d', String(value));
}

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

/**
 * Convert a hex color string into an RGB triplet string.
 *
 * @since 1.0.0
 * @param {string} hex Hex color value.
 * @return {string} Comma-separated RGB channels.
 */
function hexToRgb(hex) {
  const value = String(hex || '').replace('#', '');

  if (value.length !== 6) {
    return '79, 70, 229';
  }

  const r = Number.parseInt(value.slice(0, 2), 16);
  const g = Number.parseInt(value.slice(2, 4), 16);
  const b = Number.parseInt(value.slice(4, 6), 16);

  return `${r}, ${g}, ${b}`;
}

const rootStyle = computed(() => ({
  '--joinotify-primary': primaryColor.value,
  '--joinotify-primary-rgb': hexToRgb(primaryColor.value),
  '--joinotify-radius': borderRadius.value,
  '--joinotify-soft-0': theme.value.palette?.['0'] || '#ffffff',
  '--joinotify-soft-50': theme.value.palette?.['50'] || '#eef2ff',
  '--joinotify-soft-100': theme.value.palette?.['100'] || '#e0e7ff',
  '--joinotify-soft-200': theme.value.palette?.['200'] || '#c7d2fe',
  '--joinotify-soft-700': theme.value.palette?.['700'] || '#4338ca',
  '--joinotify-soft-900': theme.value.palette?.['900'] || '#312e81',
}));

/**
 * Resolve the request endpoint for either REST or AJAX transport.
 *
 * @since 1.0.0
 * @param {string} pathOrAction REST path or AJAX action.
 * @return {string} Endpoint URL.
 */
function requestUrl(pathOrAction) {
  if (useRestApi.value) {
    return `${apiBaseUrl.value}/${pathOrAction}`;
  }

  return window.joinotifyOtpLogin.ajaxUrl;
}

const otpJoined = computed(() => otpDigits.value.join(''));
const phonePreview = computed(() => otpPhone.value || hiddenPhone.value || visiblePhone.value);
const phoneFieldId = computed(() => `joinotify-phone-${props.context}`);
const identifierFieldId = computed(() => `joinotify-identifier-${props.context}`);
const passwordFieldId = computed(() => `joinotify-password-${props.context}`);
const rememberFieldId = computed(() => `joinotify-remember-${props.context}`);

const noticeClasses = computed(() => {
  const base = 'rounded-lg border px-4 py-3 text-sm leading-6';

  if (notice.value.type === 'success') {
    return `${base} border-emerald-200 bg-emerald-50 text-emerald-900`;
  }

  if (notice.value.type === 'error') {
    return `${base} border-rose-200 bg-rose-50 text-rose-900`;
  }

  return `${base} border-sky-200 bg-sky-50 text-sky-900`;
});

/**
 * Normalize a phone number into a plus-prefixed string.
 *
 * @since 1.0.0
 * @param {string} raw Raw phone input.
 * @return {string} Normalized phone number.
 */
function normalizePhone(raw) {
  const value = String(raw || '').trim();
  const digits = value.replace(/\D+/g, '');

  if (!digits) {
    return '';
  }

  if (value.startsWith('+')) {
    return `+${digits}`;
  }

  const dialCodeMap = {
    br: '55',
    us: '1',
    ca: '1',
    pt: '351',
    es: '34',
    fr: '33',
    de: '49',
    it: '39',
    gb: '44',
    uk: '44',
    ar: '54',
    cl: '56',
    co: '57',
    mx: '52',
    pe: '51',
    uy: '598',
    py: '595',
  };

  return `+${dialCodeMap[props.defaultCountry.toLowerCase()] || '55'}${digits}`;
}

/**
 * Store a feedback message in reactive state.
 *
 * @since 1.0.0
 * @param {string} type Notice type.
 * @param {string} message Notice text.
 * @return {void}
 */
function setNotice(type, message) {
  notice.value = { type, message };
}

/**
 * Clear the current notice message.
 *
 * @since 1.0.0
 * @return {void}
 */
function clearNotice() {
  notice.value = { type: 'info', message: '' };
}

/**
 * Toggle the loading indicator for the active request.
 *
 * @since 1.0.0
 * @param {boolean} value Loading state.
 * @return {void}
 */
function setLoadingState(value) {
  loading.value = value;
}

/**
 * Move the widget to the requested step.
 *
 * @since 1.0.0
 * @param {string} step Step name.
 * @return {void}
 */
function switchStep(step) {
  currentStep.value = step;
}

/**
 * Reset the OTP digit array to empty values.
 *
 * @since 1.0.0
 * @return {void}
 */
function resetOtpDigits() {
  otpDigits.value = Array.from({ length: props.otpLength }, () => '');
}

/**
 * Focus a specific OTP input after the DOM has updated.
 *
 * @since 1.0.0
 * @param {number} index Input index.
 * @return {void}
 */
function focusOtp(index) {
  nextTick(() => {
    const target = otpInputs.value[index];

    if (target) {
      target.focus();
      target.select();
    }
  });
}

/**
 * Sync the visible phone field into the hidden normalized field.
 *
 * @since 1.0.0
 * @return {void}
 */
function syncPhoneFromInput() {
  const value = visiblePhone.value;
  visiblePhone.value = value;
  hiddenPhone.value = normalizePhone(value);
}

/**
 * Translate the intl-tel-input validation state into a user message.
 *
 * @since 1.0.0
 * @param {string} number Normalized number.
 * @param {number|null} errorCode Validation error code.
 * @return {string} Validation message.
 */
function getPhoneErrorMessage(number, errorCode) {
  if (!number) {
    return t('Please enter a number');
  }

  const genericError = t('Invalid number');
  const validationError = intlTelInput.utils?.validationError || {};
  const errorMap = {
    [validationError.INVALID_COUNTRY_CODE]: t('Invalid country code'),
    [validationError.TOO_SHORT]: t('Too short'),
    [validationError.TOO_LONG]: t('Too long'),
    [validationError.INVALID_LENGTH]: genericError,
  };

  return errorMap[errorCode] || genericError;
}

/**
 * Validate the current phone input before requesting a code.
 *
 * @since 1.0.0
 * @return {boolean} True when the phone number is valid.
 */
function validatePhone() {
  showPhoneValidation.value = true;

  const number = phoneIti.value?.getNumber?.() || hiddenPhone.value || visiblePhone.value;
  const isValid = Boolean(phoneIti.value?.isValidNumber?.());
  const errorCode = phoneIti.value?.getValidationError?.() ?? null;

  phoneValidationMessage.value = !isValid ? getPhoneErrorMessage(number, errorCode) : '';
  syncPhoneFromInput();

  return isValid;
}

/**
 * Load the shared utils bundle used by intl-tel-input.
 *
 * @since 1.0.0
 * @return {Promise<unknown>} Dynamic import promise.
 */
function loadIntlUtils() {
  return import('intl-tel-input/utils');
}

function initPhoneInput() {
  const input = phoneField.value?.inputEl;

  if (!input) {
    syncPhoneFromInput();
    return;
  }

  phoneIti.value = intlTelInput(input, {
    initialCountry: props.defaultCountry || 'br',
    nationalMode: false,
    formatOnDisplay: true,
    autoPlaceholder: 'aggressive',
    placeholderNumberType: 'MOBILE',
    containerClass: 'w-full',
    ...getIntlTelInputLocale(),
    loadUtils: loadIntlUtils,
  });

  syncPhoneFromInput();
}

function readPhoneValue() {
  if (phoneIti.value && typeof phoneIti.value.getNumber === 'function') {
    const number = phoneIti.value.getNumber();

    if (number) {
      return number;
    }
  }

  return normalizePhone(visiblePhone.value);
}

function fillOtpDigits(value) {
  const digits = String(value || '').replace(/\D+/g, '').slice(0, props.otpLength);
  otpDigits.value = Array.from({ length: props.otpLength }, (_, index) => digits[index] || '');

  if (digits.length < props.otpLength) {
    focusOtp(digits.length);
  }
}

async function requestOtp() {
  if (!validatePhone()) {
    setNotice('error', phoneValidationMessage.value || t('Enter a valid phone number with country code.'));
    return;
  }

  const phone = readPhoneValue();

  if (!phone) {
    setNotice('error', t('Enter a valid phone number with country code.'));
    return;
  }

  setLoadingState(true);
  clearNotice();

  try {
    const response = await window.fetch(requestUrl('request-code'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-WP-Nonce': window.joinotifyOtpLogin.nonce,
      },
      body: new URLSearchParams({
        ...(useRestApi.value
          ? { phone }
          : { action: 'joinotify_otp_request_code', nonce: window.joinotifyOtpLogin.legacyNonce, phone }),
      }),
    });

    const payload = await response.json();

    if (!payload.success) {
      setNotice('error', payload.data?.message || t('We could not complete the request right now. Please try again.'));
      return;
    }

    hiddenPhone.value = payload.data.phone || phone;
    otpPhone.value = payload.data.phone || phone;

    if (payload.data.nextStep === 'password') {
      setNotice('info', payload.data.message);
      switchStep(steps.password);
      return;
    }

    setNotice('success', payload.data.message);
    switchStep(steps.otp);
    resetOtpDigits();
    startCountdown();
    await nextTick();
    focusOtp(0);
  } catch (error) {
    setNotice('error', t('We could not complete the request right now. Please try again.'));
  } finally {
    setLoadingState(false);
  }
}

async function verifyOtp() {
  const otp = otpJoined.value;
  const phone = hiddenPhone.value || otpPhone.value;

  if (!phone || otp.length !== props.otpLength) {
    setNotice('error', t('Enter the verification code you received.'));
    focusOtp(otpDigits.value.findIndex((digit) => !digit));
    return;
  }

  if (loading.value) {
    return;
  }

  setLoadingState(true);

  try {
    const response = await window.fetch(requestUrl('verify-code'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-WP-Nonce': window.joinotifyOtpLogin.nonce,
      },
      body: new URLSearchParams({
        ...(useRestApi.value
          ? { phone, otp, remember: remember.value ? '1' : '0', redirect: props.redirectUrl }
          : {
              action: 'joinotify_otp_verify_code',
              nonce: window.joinotifyOtpLogin.legacyNonce,
              phone,
              otp,
              remember: remember.value ? '1' : '0',
              redirect: props.redirectUrl,
            }),
      }),
    });

    const payload = await response.json();

    if (!payload.success) {
      setNotice('error', payload.data?.message || t('We could not complete the request right now. Please try again.'));
      return;
    }

    window.location.href = payload.data.redirect;
  } catch (error) {
    setNotice('error', t('We could not complete the request right now. Please try again.'));
  } finally {
    setLoadingState(false);
  }
}

async function loginWithPassword() {
  if (!identifier.value || !password.value) {
    setNotice('error', t('Fill in the email or username and password.'));
    return;
  }

  setLoadingState(true);

  try {
    const response = await window.fetch(requestUrl('password-login'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-WP-Nonce': window.joinotifyOtpLogin.nonce,
      },
      body: new URLSearchParams({
        ...(useRestApi.value
          ? {
              identifier: identifier.value,
              password: password.value,
              remember: remember.value ? '1' : '0',
              redirect: props.redirectUrl,
            }
          : {
              action: 'joinotify_otp_password_login',
              nonce: window.joinotifyOtpLogin.legacyNonce,
              identifier: identifier.value,
              password: password.value,
              remember: remember.value ? '1' : '0',
              redirect: props.redirectUrl,
            }),
      }),
    });

    const payload = await response.json();

    if (!payload.success) {
      setNotice('error', payload.data?.message || t('We could not complete the request right now. Please try again.'));
      return;
    }

    window.location.href = payload.data.redirect;
  } catch (error) {
    setNotice('error', t('We could not complete the request right now. Please try again.'));
  } finally {
    setLoadingState(false);
  }
}

function startCountdown() {
  resendEnabled.value = false;
  countdown.value = 60;

  if (resendTimer.value) {
    clearInterval(resendTimer.value);
  }

  resendTimer.value = setInterval(() => {
    countdown.value -= 1;

    if (countdown.value <= 0) {
      resendEnabled.value = true;
      clearInterval(resendTimer.value);
      resendTimer.value = null;
    }
  }, 1000);
}

function handleOtpInput(index, event) {
  const value = String(event.target.value || '').replace(/\D+/g, '').slice(-1);
  otpDigits.value[index] = value;
  event.target.value = value;

  if (value && index < props.otpLength - 1) {
    focusOtp(index + 1);
  }

  if (otpDigits.value.every(Boolean)) {
    verifyOtp();
  }
}

function handleOtpKeydown(index, event) {
  if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
    focusOtp(index - 1);
  }
}

function handleOtpPaste(index, event) {
  const pasted = String(event.clipboardData?.getData('text') || '').replace(/\D+/g, '').slice(0, props.otpLength);

  if (!pasted) {
    return;
  }

  event.preventDefault();
  otpDigits.value = Array.from({ length: props.otpLength }, (_, position) => pasted[position] || '');

  if (pasted.length === props.otpLength) {
    verifyOtp();
    return;
  }

  focusOtp(Math.min(index + pasted.length, props.otpLength - 1));
}

function resendOtp() {
  requestOtp();
}

function setOtpInputRef(el, index) {
  otpInputs.value[index] = el;
}

onMounted(() => {
  initPhoneInput();
});

onBeforeUnmount(() => {
  if (resendTimer.value) {
    clearInterval(resendTimer.value);
  }
});
</script>

<template>
  <div
    ref="scope"
    :style="rootStyle"
    class="joinotify-otp-login__surface relative isolate w-full shadow-none bg-white/95"
  >
    <div class="relative z-10 mx-auto flex w-full max-w-md flex-col gap-6">
      <div v-if="showHeader" class="space-y-2 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">
          {{ t('Secure access') }}
        </p>
        <h2 class="joinotify-otp-login__title text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
          {{ title || t('Log in with WhatsApp') }}
        </h2>
        <p v-if="description" class="joinotify-otp-login__description text-sm leading-6 text-slate-500 sm:text-base">
          {{ description }}
        </p>
      </div>

      <div v-if="notice.message" :class="noticeClasses">
        {{ notice.message }}
      </div>

      <section v-show="currentStep === steps.phone" class="space-y-5">
        <form class="space-y-5" @submit.prevent="requestOtp">
          <PhoneField
            ref="phoneField"
            v-model="visiblePhone"
            :field-id="phoneFieldId"
            :helper="t('Enter a valid phone number. The country code will be detected automatically.')"
            :label="t('Phone number')"
            :show-validation="showPhoneValidation"
            :validation-message="phoneValidationMessage"
            @blur="validatePhone"
            @change="syncPhoneFromInput"
            @countrychange="syncPhoneFromInput"
            @input="syncPhoneFromInput"
          />

          <div class="flex flex-col gap-5">
            <BaseButton
              :disabled="loading"
              type="submit"
            >
              {{ loading ? t('Sending...') : t('Log in with WhatsApp') }}
            </BaseButton>
            <BaseButton
              kind="secondary"
              type="button"
              @click="switchStep(steps.password)"
            >
              {{ t('Use email and password') }}
            </BaseButton>
          </div>
        </form>
      </section>

      <section v-show="currentStep === steps.otp" class="space-y-5">
        <form class="space-y-5" @submit.prevent="verifyOtp">
          <input v-model="hiddenPhone" type="hidden" name="phone" />
          <input :value="redirectUrl" type="hidden" name="redirect" />
          <input :value="otpJoined" type="hidden" name="otp" />

          <div class="space-y-2 text-center">
            <h3 class="text-xl font-semibold tracking-tight text-slate-900">
              {{ t('Enter the access code') }}
            </h3>
            <p class="text-sm leading-6 text-slate-500">
              {{ tWithCount('Enter the %d-digit code sent to your WhatsApp.', props.otpLength) }}
            </p>
            <p class="text-sm font-medium text-slate-700">
              {{ phonePreview }}
            </p>
          </div>

          <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 sm:gap-3">
            <input
              v-for="(_, index) in otpLength"
              :key="index"
              :ref="(el) => setOtpInputRef(el, index)"
              :value="otpDigits[index]"
              class="joinotify-otp-login__input h-14 border border-slate-200 bg-slate-50 text-center text-xl font-semibold text-slate-900 outline-none transition placeholder:text-slate-400"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="1"
              :aria-label="tWithCount('Code digit %d', index + 1)"
              @input="handleOtpInput(index, $event)"
              @keydown="handleOtpKeydown(index, $event)"
              @paste="handleOtpPaste(index, $event)"
            />
          </div>

          <FormCheckbox
            v-model="remember"
            :id="rememberFieldId"
            :label="t('Remember me')"
            name="remember"
          />

          <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <template v-if="!resendEnabled">
              <span>{{ t('Resend code in') }}</span>
              <span class="font-semibold text-slate-700">{{ countdown }}</span>
              <span>{{ t('seconds') }}</span>
            </template>
            <button
              v-else
              class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
              type="button"
              @click="resendOtp"
            >
              {{ t('Resend code') }}
            </button>
          </div>

          <div class="grid gap-3">
            <BaseButton
              :disabled="loading"
              type="submit"
            >
              <Loader v-if="loading" />
              <span v-else>{{ t('Verify OTP') }}</span>
            </BaseButton>
            <BaseButton
              kind="secondary"
              type="button"
              @click="switchStep(steps.phone)"
            >
              {{ t('Change number') }}
            </BaseButton>
          </div>
        </form>
      </section>

      <section v-show="currentStep === steps.password" class="space-y-5">
        <div class="text-center">
          <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
            {{ t('Or sign in with email') }}
          </div>
        </div>

        <form class="space-y-5" @submit.prevent="loginWithPassword">
          <Field :for-id="identifierFieldId" :label="t('Email or username')">
            <input
              :id="identifierFieldId"
              v-model="identifier"
              type="text"
              class="joinotify-otp-login__input w-full border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400"
              autocomplete="username"
              :placeholder="t('Enter your email or username')"
            />
          </Field>

          <Field :for-id="passwordFieldId" :label="t('Password')">
            <div class="relative">
              <input
                :id="passwordFieldId"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                class="joinotify-otp-login__input w-full border border-slate-200 bg-slate-50 px-4 py-3 pr-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400"
                autocomplete="current-password"
                :placeholder="t('Enter your password')"
              />
              <button
                class="absolute outline-none inset-y-0 right-0 flex items-center px-4 text-slate-500 transition hover:text-slate-900"
                type="button"
                :aria-label="showPassword ? t('Hide password') : t('Show password')"
                @click="showPassword = !showPassword"
              >
                <svg
                  v-if="!showPassword"
                  aria-hidden="true"
                  class="h-5 w-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    d="M2.95862 12.951C2.68046 12.3479 2.68046 11.6523 2.95862 11.0492C4.53779 7.6253 7.99237 5.25 11.9999 5.25C16.0075 5.25 19.4621 7.62531 21.0413 11.0492C21.3194 11.6523 21.3194 12.3479 21.0413 12.951C19.4621 16.3749 16.0075 18.7502 11.9999 18.7502C7.99237 18.7502 4.53779 16.3749 2.95862 12.951Z"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                  />
                  <path
                    d="M15.625 12C15.625 14.002 14.002 15.625 12 15.625C9.99797 15.625 8.375 14.002 8.375 12C8.375 9.99797 9.99797 8.375 12 8.375C14.002 8.375 15.625 9.99797 15.625 12Z"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                  />
                </svg>
                <svg
                  v-else
                  aria-hidden="true"
                  class="h-5 w-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path d="M3 3L21 21" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" />
                  <path
                    d="M10.58 10.58A3 3 0 0013.42 13.42"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                  />
                  <path
                    d="M9.88 5.08A10.4 10.4 0 0112 4.75C16.0075 4.75 19.4621 7.12531 21.0413 10.5492C21.3194 11.1523 21.3194 11.8479 21.0413 12.451C20.3337 14.0046 19.234 15.31 17.866 16.25"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                  />
                  <path
                    d="M6.12 6.12C4.57872 7.32617 3.30035 8.93047 2.95862 10.0492C2.68046 10.6523 2.68046 11.3479 2.95862 11.951C4.53779 15.3749 7.99237 17.7502 11.9999 17.7502C13.5083 17.7502 14.9295 17.4353 16.16 16.87"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                  />
                </svg>
              </button>
            </div>
          </Field>

          <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
            <FormCheckbox
              v-model="remember"
              :id="rememberFieldId"
              :label="t('Remember me')"
              name="remember"
            />
            <a class="joinotify-otp-login__forgot-link font-semibold transition" :href="lostPasswordUrl">
              {{ t('Forgot your password?') }}
            </a>
          </div>

          <div class="flex flex-col gap-3">
            <BaseButton
              :disabled="loading"
              type="submit"
            >
              <Loader v-if="loading" />
              <span v-else>{{ t('Sign in') }}</span>
            </BaseButton>
            <BaseButton
              kind="secondary"
              type="button"
              @click="switchStep(steps.phone)"
            >
              {{ t('Back to WhatsApp') }}
            </BaseButton>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>
