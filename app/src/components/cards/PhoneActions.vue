<script setup>
import { computed, ref, watch } from 'vue';
import { __, textDomain } from '../../lib/i18n';
import ModalDialog from '../base/ModalDialog.vue';
import SelectField from '../fields/SelectField.vue';
import OtpField from '../fields/OtpField.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  candidates: { type: Array, default: () => [] },
  senders: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'register', 'validate', 'test-message']);

const registerOpen = ref(false);
const registerStep = ref('select');
const registerPhone = ref('');
const otpDigits = ref(Array(4).fill(''));
const otpComplete = ref(false);
const defaultSender = computed(() => (props.senders[0] ? props.senders[0].phone : ''));

watch(
  () => props.candidates,
  (value) => {
    if (!registerPhone.value && value.length) {
      registerPhone.value = value[0].phone;
    }
  },
  { immediate: true }
);

watch(registerPhone, () => {
  otpDigits.value = Array(4).fill('');
  otpComplete.value = false;
  registerStep.value = 'select';
});

watch(registerOpen, (open) => {
  if (!open) {
    registerStep.value = 'select';
    otpDigits.value = Array(4).fill('');
    otpComplete.value = false;
  }
});

function sendOtp() {
  if (!registerPhone.value) {
    return;
  }

  emit('register', registerPhone.value);
  registerStep.value = 'otp';
}

function validate() {
  const otpCode = otpDigits.value.join('');

  if (!registerPhone.value || !otpComplete.value || otpCode.length !== 4) {
    return;
  }

  emit('validate', { phone: registerPhone.value, otp: otpCode });
  registerOpen.value = false;
}
</script>

<template>
  <div class="space-y-6">
    <div class="grid items-center gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)]">
      <div>
        <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Test phone number', textDomain) }}</h3>
        <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
          {{ __('Enter a phone number to receive test messages from the builder. Use international format and numbers only.', textDomain) }}
        </p>
      </div>

      <div class="lg:justify-self-start">
        <input
          :value="modelValue"
          type="text"
          inputmode="numeric"
          :placeholder="__('5541987111527', textDomain)"
          class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100 md:min-w-[430px]"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button
        type="button"
        class="rounded-[8px] bg-primary-700 px-5 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-800"
        @click="registerOpen = true"
      >
        {{ __('Add new phone', textDomain) }}
      </button>
      <button
        type="button"
        class="rounded-[8px] border border-primary-200 bg-white px-5 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="!modelValue || !defaultSender"
        @click="$emit('test-message', { sender: defaultSender, receiver: modelValue, message: __('Hello, this is a test message.', textDomain) })"
      >
        {{ __('Send test message', textDomain) }}
      </button>
    </div>

    <ModalDialog
      :open="registerOpen"
      :title="__('Register new phone', textDomain)"
      :description="registerStep === 'select' ? __('Step 1: Select an available phone.', textDomain) : __('Step 2: Enter the verification code.', textDomain)"
      :eyebrow="__('Phones', textDomain)"
      @close="registerOpen = false"
    >
      <div class="space-y-4">
        <div class="space-y-3">
          <div>
            <span class="text-sm font-medium text-ink">{{ __('Step 1: Select an available phone', textDomain) }}</span>
          </div>

          <SelectField
            v-model="registerPhone"
            :field="{
              label: __('Candidate phone', textDomain),
              description: __('Select an available phone for registration and validation.', textDomain),
              placeholder: __('Select a phone', textDomain),
              emptyLabel: __('No phone available', textDomain),
              searchable: true,
              options: candidates.map((item) => ({
                value: item.phone,
                label: item.formatted || item.phone,
                meta: item.phone,
              })),
            }"
            name="register-phone"
          />
        </div>

        <div v-if="registerStep === 'otp'" class="space-y-3">
          <div>
            <span class="text-sm font-medium text-ink">{{ __('Step 2: Enter the verification code', textDomain) }}</span>
            <p class="mt-1 text-sm leading-6 text-muted">
              {{ __('Enter the code received on the selected phone.', textDomain) }}
            </p>
          </div>

          <OtpField
            v-model:digits="otpDigits"
            :length="4"
            @complete="otpComplete = true"
          />
        </div>

        <div class="flex flex-wrap justify-end gap-3">
          <button
            type="button"
            class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            @click="registerOpen = false"
          >
            {{ __('Cancel', textDomain) }}
          </button>
          <button
            v-if="registerStep === 'select'"
            type="button"
            class="rounded-full bg-shell-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-shell-700 disabled:cursor-not-allowed disabled:bg-slate-300"
            :disabled="!registerPhone"
            @click="sendOtp"
          >
            {{ __('Register phone', textDomain) }}
          </button>
          <button
            v-else
            type="button"
            class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-300"
            :disabled="!registerPhone || !otpComplete"
            @click="validate"
          >
            {{ __('Validate and save', textDomain) }}
          </button>
        </div>
      </div>
    </ModalDialog>
  </div>
</template>
