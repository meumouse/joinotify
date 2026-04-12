<script setup>
import { computed, ref, watch } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import SelectField from '../../../../components/fields/SelectField.vue';
import TextAreaField from '../../../../components/fields/TextAreaField.vue';
import BaseButton from '../../../../components/buttons/BaseButton.vue';
import PhoneField from '../../../../components/fields/PhoneField.vue';
import OtpField from '../../../../components/fields/OtpField.vue';

const DEFAULT_TEST_MESSAGE = __('Hello, this is a test message.', textDomain);

const props = defineProps({
  modelValue: { type: String, default: '' },
  candidates: { type: Array, default: () => [] },
  senders: { type: Array, default: () => [] },
  defaultCountry: { type: String, default: 'us' },
  sendTestMessage: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'register', 'validate']);

const registerOpen = ref(false);
const registerStep = ref('select');
const registerPhone = ref('');
const otpDigits = ref(Array(4).fill(''));
const otpComplete = ref(false);
const testMessageOpen = ref(false);
const selectedSender = ref('');
const testReceiverPhone = ref('');
const testMessageBody = ref(DEFAULT_TEST_MESSAGE);
const sendingTestMessage = ref(false);

const senderOptions = computed(() =>
  (Array.isArray(props.senders) ? props.senders : []).map((sender) => ({
    value: sender.phone,
    label: sender.formatted || sender.phone,
    meta: sender.connection === 'connected' ? __('Connected', textDomain) : __('Disconnected', textDomain),
  }))
);

watch(
  () => props.candidates,
  (value) => {
    if (!registerPhone.value && value.length) {
      registerPhone.value = value[0].phone;
    }
  },
  { immediate: true }
);

watch(
  () => props.senders,
  () => {
    if (!senderOptions.value.length) {
      selectedSender.value = '';
      return;
    }

    const hasSelectedSender = senderOptions.value.some((option) => option.value === selectedSender.value);

    if (!hasSelectedSender) {
      selectedSender.value = senderOptions.value[0].value;
    }
  },
  { immediate: true, deep: true }
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

watch(testMessageOpen, (open) => {
  if (open) {
    selectedSender.value = senderOptions.value.find((option) => option.value === selectedSender.value)?.value || senderOptions.value[0]?.value || '';
    testReceiverPhone.value = props.modelValue;
    testMessageBody.value = DEFAULT_TEST_MESSAGE;
    return;
  }

  sendingTestMessage.value = false;
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

function openTestMessageModal() {
  if (!senderOptions.value.length) {
    return;
  }

  if (!selectedSender.value) {
    selectedSender.value = senderOptions.value[0].value;
  }

  testMessageOpen.value = true;
}

async function submitTestMessage() {
  if (sendingTestMessage.value || !props.sendTestMessage || !selectedSender.value || !testReceiverPhone.value || !testMessageBody.value.trim()) {
    return;
  }

  sendingTestMessage.value = true;

  try {
    const response = await props.sendTestMessage({
      sender: selectedSender.value,
      receiver: testReceiverPhone.value,
      message: testMessageBody.value.trim(),
    });

    if (response !== false) {
      testMessageOpen.value = false;
    }
  } finally {
    sendingTestMessage.value = false;
  }
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

      <div class="lg:justify-self-start md:min-w-[430px]">
        <PhoneField
          :model-value="modelValue"
          :field="{
            placeholder: __('5541987111527', textDomain),
          }"
          :default-country="defaultCountry"
          :show-header="false"
          name="test-number-phone"
          @update:model-value="$emit('update:modelValue', $event)"
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
        :disabled="!senderOptions.length || !sendTestMessage"
        @click="openTestMessageModal"
      >
        {{ __('Send test message', textDomain) }}
      </button>
    </div>

    <ModalDialog
      :open="testMessageOpen"
      :title="__('Send test message', textDomain)"
      :description="__('Choose the sender and review the message before sending a test WhatsApp message.', textDomain)"
      :eyebrow="__('Phones', textDomain)"
      size-class="max-w-3xl"
      @close="testMessageOpen = false"
    >
      <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
          <div>
            <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Test sender', textDomain) }}</h4>
            <p class="mt-1 text-[13px] leading-5 text-slate-500">
              {{ __('Select the WhatsApp sender that will be used to deliver this test message.', textDomain) }}
            </p>
          </div>

          <SelectField
            v-model="selectedSender"
            :field="{
              label: __('Sender', textDomain),
              description: __('Choose one of the validated senders available in your account.', textDomain),
              placeholder: __('Select a sender', textDomain),
              emptyLabel: __('No sender available', textDomain),
              searchable: true,
              options: senderOptions,
            }"
            name="test-message-sender"
          />
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
          <div>
            <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Recipient', textDomain) }}</h4>
            <p class="mt-1 text-[13px] leading-5 text-slate-500">
              {{ __('Enter the destination phone number that will receive the test message.', textDomain) }}
            </p>
          </div>

          <PhoneField
            v-model="testReceiverPhone"
            :field="{
              label: __('Recipient phone', textDomain),
              description: __('Phone number that will receive the test WhatsApp message.', textDomain),
              placeholder: __('5541987111527', textDomain),
            }"
            :default-country="defaultCountry"
            :show-header="false"
            name="test-message-receiver"
          />
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)]">
          <div>
            <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Message', textDomain) }}</h4>
            <p class="mt-1 text-[13px] leading-5 text-slate-500">
              {{ __('The message is prefilled and can be adjusted before sending the test.', textDomain) }}
            </p>
          </div>

          <TextAreaField
            v-model="testMessageBody"
            :field="{
              placeholder: __('Type your test message...', textDomain),
              rows: 3,
            }"
            name="test-message-body"
            :show-header="false"
          />
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
          <button
            type="button"
            class="rounded-[8px] border border-slate-200 bg-white px-5 py-3 text-[14px] font-semibold text-slate-700 transition hover:bg-slate-50"
            :disabled="sendingTestMessage"
            @click="testMessageOpen = false"
          >
            {{ __('Cancelar', textDomain) }}
          </button>

          <BaseButton
            :title="__('Enviar', textDomain)"
            color="primary"
            size="lg"
            :loading="sendingTestMessage"
            :disabled="!selectedSender || !testReceiverPhone || !testMessageBody.trim() || !sendTestMessage"
            @click="submitTestMessage"
          />
        </div>
      </div>
    </ModalDialog>

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
