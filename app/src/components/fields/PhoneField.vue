<script setup>

/**
 * PhoneField.vue frontend component.
 *
 * @since 1.4.7
 * @version 2.0.0
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import { __, textDomain } from '../../utils/i18n';
import flags1xUrl from 'intl-tel-input/build/img/flags.webp?url';
import flags2xUrl from 'intl-tel-input/build/img/flags@2x.webp?url';

const props = defineProps({
  modelValue: { type: String, default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  defaultCountry: { type: String, default: 'us' },
  showHeader: { type: Boolean, default: true },
  locale: { type: String, default: 'en_US' },
});

const emit = defineEmits(['update:modelValue']);

const inputEl = ref(null);
const iti = ref(null);
const isSyncingFromModel = ref(false);
let onCountryChange = null;

const localeLoaders = {
  ar: () => import('intl-tel-input/i18n/ar'),
  bg: () => import('intl-tel-input/i18n/bg'),
  bn: () => import('intl-tel-input/i18n/bn'),
  bs: () => import('intl-tel-input/i18n/bs'),
  ca: () => import('intl-tel-input/i18n/ca'),
  cs: () => import('intl-tel-input/i18n/cs'),
  da: () => import('intl-tel-input/i18n/da'),
  de: () => import('intl-tel-input/i18n/de'),
  ee: () => import('intl-tel-input/i18n/ee'),
  el: () => import('intl-tel-input/i18n/el'),
  en: () => import('intl-tel-input/i18n/en'),
  es: () => import('intl-tel-input/i18n/es'),
  fa: () => import('intl-tel-input/i18n/fa'),
  fi: () => import('intl-tel-input/i18n/fi'),
  fr: () => import('intl-tel-input/i18n/fr'),
  hi: () => import('intl-tel-input/i18n/hi'),
  hr: () => import('intl-tel-input/i18n/hr'),
  hu: () => import('intl-tel-input/i18n/hu'),
  id: () => import('intl-tel-input/i18n/id'),
  it: () => import('intl-tel-input/i18n/it'),
  ja: () => import('intl-tel-input/i18n/ja'),
  ko: () => import('intl-tel-input/i18n/ko'),
  lt: () => import('intl-tel-input/i18n/lt'),
  mr: () => import('intl-tel-input/i18n/mr'),
  nl: () => import('intl-tel-input/i18n/nl'),
  no: () => import('intl-tel-input/i18n/no'),
  pl: () => import('intl-tel-input/i18n/pl'),
  pt: () => import('intl-tel-input/i18n/pt'),
  ro: () => import('intl-tel-input/i18n/ro'),
  ru: () => import('intl-tel-input/i18n/ru'),
  sk: () => import('intl-tel-input/i18n/sk'),
  sl: () => import('intl-tel-input/i18n/sl'),
  sq: () => import('intl-tel-input/i18n/sq'),
  sr: () => import('intl-tel-input/i18n/sr'),
  sv: () => import('intl-tel-input/i18n/sv'),
  te: () => import('intl-tel-input/i18n/te'),
  th: () => import('intl-tel-input/i18n/th'),
  tr: () => import('intl-tel-input/i18n/tr'),
  uk: () => import('intl-tel-input/i18n/uk'),
  ur: () => import('intl-tel-input/i18n/ur'),
  uz: () => import('intl-tel-input/i18n/uz'),
  vi: () => import('intl-tel-input/i18n/vi'),
  zh: () => import('intl-tel-input/i18n/zh'),
  'zh-hk': () => import('intl-tel-input/i18n/zh-hk'),
};

const fieldStyles = computed(() => ({
  '--iti-path-flags-1x': `url(${flags1xUrl})`,
  '--iti-path-flags-2x': `url(${flags2xUrl})`,
}));

function normalizeLocale(locale) {
  if (!locale) {
    return 'en';
  }

  return String(locale).trim().replace('_', '-').toLowerCase();
}

function toCountryNameLocale(locale) {
  const normalized = String(locale || '').trim().replace('_', '-');

  if (!normalized) {
    return 'en';
  }

  const parts = normalized.split('-');

  if (parts.length < 2) {
    return parts[0].toLowerCase();
  }

  const [language, region, ...rest] = parts;
  return [language.toLowerCase(), region.toUpperCase(), ...rest].join('-');
}

function getIntlTelInputLoader(locale) {
  const normalized = normalizeLocale(locale);
  const primary = normalized.split('-')[0];

  if (localeLoaders[normalized]) {
    return localeLoaders[normalized];
  }

  if (localeLoaders[primary]) {
    return localeLoaders[primary];
  }

  return localeLoaders.en;
}

async function loadIntlTelInputTranslations(locale) {
  const module = await getIntlTelInputLoader(locale)();

  return module.default || module;
}

function getInitialNumber(value) {
  if (!value) {
    return '';
  }

  if (String(value).trim().startsWith('+')) {
    return String(value).trim();
  }

  return `+${String(value).trim()}`;
}

function applyModelValue(value) {
  if (!iti.value || !inputEl.value) {
    return;
  }

  const nextValue = getInitialNumber(value);

  if (nextValue) {
    isSyncingFromModel.value = true;
    iti.value.setNumber(nextValue);
    isSyncingFromModel.value = false;
    return;
  }

  iti.value.setNumber('');
}

function handleCountryChange() {
  if (isSyncingFromModel.value || !iti.value) {
    return;
  }

  // When the field is still empty, prefill it with the selected country's dial code (DDI).
  if (!inputEl.value.value.trim()) {
    const country = iti.value.getSelectedCountryData();

    if (country && country.dialCode) {
      iti.value.setNumber(`+${country.dialCode}`);
    }
  }

  const number = iti.value.getNumber();
  emit('update:modelValue', number || inputEl.value.value.trim());
}

onMounted(() => {
  if (!inputEl.value) {
    return;
  }

  loadIntlTelInputTranslations(props.locale)
    .then((translations) => {
      if (!inputEl.value) {
        return;
      }

        iti.value = intlTelInput(inputEl.value, {
          initialCountry: props.defaultCountry || 'us',
          nationalMode: false,
          autoPlaceholder: 'polite',
          formatOnDisplay: true,
          separateDialCode: false,
          countryNameLocale: toCountryNameLocale(props.locale),
          i18n: translations,
          loadUtils: () => import('intl-tel-input/utils'),
        });

      applyModelValue(props.modelValue);

      onCountryChange = handleCountryChange;
      inputEl.value.addEventListener('countrychange', onCountryChange);
    })
    .catch(() => {
      if (!inputEl.value) {
        return;
      }

        iti.value = intlTelInput(inputEl.value, {
          initialCountry: props.defaultCountry || 'us',
          nationalMode: false,
          autoPlaceholder: 'polite',
          formatOnDisplay: true,
          separateDialCode: false,
          countryNameLocale: toCountryNameLocale(props.locale),
          loadUtils: () => import('intl-tel-input/utils'),
        });

      applyModelValue(props.modelValue);

      onCountryChange = handleCountryChange;
      inputEl.value.addEventListener('countrychange', onCountryChange);
    });
});

watch(
  () => props.modelValue,
  (value) => {
    applyModelValue(value);
  }
);

onBeforeUnmount(() => {
  if (inputEl.value && onCountryChange) {
    inputEl.value.removeEventListener('countrychange', onCountryChange);
  }

  if (iti.value) {
    iti.value.destroy();
    iti.value = null;
  }
});

function handleInput() {
  if (isSyncingFromModel.value || !iti.value) {
    return;
  }

  const number = iti.value.getNumber();
  emit('update:modelValue', number || inputEl.value.value.trim());
}
</script>

<template>
  <label class="block">
    <template v-if="showHeader">
      <span class="text-sm font-medium text-ink">{{ field.label }}</span>
      <p v-if="field.description" class="mt-1 text-sm leading-6 text-muted">
        {{ field.description }}
      </p>
    </template>

    <div class="joinotify-phone-field" :class="showHeader ? 'mt-3' : ''" :style="fieldStyles">
      <input
        ref="inputEl"
        :name="name"
        type="tel"
        :placeholder="field.placeholder || __('Enter phone number', textDomain)"
        class="joinotify-phone-field__input w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-[16px] leading-6 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:bg-white focus:ring-4 focus:ring-primary-100 md:px-5 md:py-5 md:text-[17px]"
        autocomplete="tel"
        inputmode="tel"
        @input="handleInput"
      />
    </div>
  </label>
</template>
