<script setup>

/**
 * PhoneActions.vue frontend component.
 *
 * On the Cloud API transport a number is connected on the Joinotify panel
 * through Meta's Embedded Signup, so the slot + OTP onboarding is replaced by a
 * read-only import of whatever the account already has.
 *
 * @since 1.4.7
 * @version 2.3.0
 */
import { computed, ref, watch } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import SelectField from '../../../../components/fields/SelectField.vue';
import RichTextAreaField from '../../../../components/fields/RichTextAreaField.vue';
import BaseButton from '../../../../components/buttons/BaseButton.vue';
import PhoneField from '../../../../components/fields/PhoneField.vue';

const DEFAULT_TEST_MESSAGE = __('Hello, this is a test message.', textDomain);

const props = defineProps({
  modelValue: { type: String, default: '' },
  senders: { type: Array, default: () => [] },
  defaultCountry: { type: String, default: 'us' },
  locale: { type: String, default: 'en_US' },
  sendTestMessage: { type: Function, default: null },
  senderActionLoading: { type: Boolean, default: false },
  panelUrl: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'sync']);

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

watch(testMessageOpen, (open) => {
  if (open) {
    selectedSender.value = senderOptions.value.find((option) => option.value === selectedSender.value)?.value || senderOptions.value[0]?.value || '';
    testReceiverPhone.value = props.modelValue;
    testMessageBody.value = DEFAULT_TEST_MESSAGE;
    return;
  }

  sendingTestMessage.value = false;
});

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
          :locale="locale"
          :show-header="false"
          name="test-number-phone"
          @update:model-value="$emit('update:modelValue', $event)"
        />
      </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 px-5 py-4 text-[13px] leading-5 text-slate-600">
      {{ __('Your numbers are connected on the Joinotify panel. Sync to import them here, along with their verified name, quality rating and 24-hour messaging limit.', textDomain) }}
      <a
        v-if="panelUrl"
        class="ms-1 font-medium text-primary-700 underline underline-offset-2"
        :href="panelUrl"
        target="_blank"
        rel="noopener noreferrer"
      >{{ __('Open the panel', textDomain) }}</a>
    </div>

    <div class="flex flex-wrap gap-3">
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-[8px] bg-primary-700 px-5 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-70"
        :disabled="senderActionLoading"
        @click="$emit('sync')"
      >
        <span v-if="senderActionLoading" class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" />
        {{ __('Sync numbers from Joinotify', textDomain) }}
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
            :locale="locale"
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

          <RichTextAreaField
            v-model="testMessageBody"
            :field="{
              placeholder: __('Type your test message...', textDomain),
              rows: 2,
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
          {{ __('Cancel', textDomain) }}
          </button>

          <BaseButton
            :title="__('Send', textDomain)"
            color="primary"
            size="lg"
            :loading="sendingTestMessage"
            :disabled="!selectedSender || !testReceiverPhone || !testMessageBody.trim() || !sendTestMessage"
            @click="submitTestMessage"
          />
        </div>
      </div>
    </ModalDialog>
  </div>
</template>
