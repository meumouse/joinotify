<script setup>
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
});

const emit = defineEmits(['update:modelValue']);

const inputEl = ref(null);
const iti = ref(null);
const isSyncingFromModel = ref(false);
let onCountryChange = null;

const fieldStyles = computed(() => ({
  '--iti-path-flags-1x': `url(${flags1xUrl})`,
  '--iti-path-flags-2x': `url(${flags2xUrl})`,
}));

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

onMounted(() => {
  if (!inputEl.value) {
    return;
  }

  iti.value = intlTelInput(inputEl.value, {
    initialCountry: props.defaultCountry || 'us',
    nationalMode: false,
    autoPlaceholder: 'polite',
    formatOnDisplay: true,
    separateDialCode: false,
    loadUtils: () => import('intl-tel-input/utils'),
  });

  applyModelValue(props.modelValue);

  onCountryChange = () => {
    if (isSyncingFromModel.value || !iti.value) {
      return;
    }

    const number = iti.value.getNumber();
    emit('update:modelValue', number || inputEl.value.value.trim());
  };

  inputEl.value.addEventListener('countrychange', onCountryChange);
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
