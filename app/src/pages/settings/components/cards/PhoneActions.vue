<script setup>

/**
 * PhoneActions.vue frontend component.
 *
 * On the Cloud API transport a number is connected on the Joinotify panel
 * through Meta's Embedded Signup, so the slot + OTP onboarding is replaced by a
 * read-only import of whatever the account already has.
 *
 * The test message is either free text or an approved template. Free text
 * only reaches a contact whose 24-hour window is open, so choosing a recipient
 * asks for the window and, when it is closed, suggests a template instead.
 *
 * @since 1.4.7
 * @version 2.8.0
 */
import { computed, inject, onBeforeUnmount, ref, watch } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import SelectField from '../../../../components/fields/SelectField.vue';
import RichTextAreaField from '../../../../components/fields/RichTextAreaField.vue';
import BaseButton from '../../../../components/buttons/BaseButton.vue';
import BaseTabs from '../../../../components/base/BaseTabs.vue';
import PhoneField from '../../../../components/fields/PhoneField.vue';
import TemplatePreview from '../../../../components/whatsapp/TemplatePreview.vue';
import TemplateVariablesForm from '../../../../components/whatsapp/TemplateVariablesForm.vue';
import { useWhatsappTemplates } from '../../../../composables/useWhatsappTemplates';
import { buildVariableMap, isRequiredSlot } from '../../../../utils/whatsappTemplates';

const DEFAULT_TEST_MESSAGE = __('Hello, this is a test message.', textDomain);

// Wait for the number to stop changing before asking for its window.
const WINDOW_CHECK_DELAY = 600;

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

const api = inject('joinotifyApi', null);
const selectedSender = ref('');

// Meta looks a template up on the account of the number that sends it, so the
// list is the one of the chosen sender's account.
const selectedSenderWaba = computed(() => {
  const sender = (Array.isArray(props.senders) ? props.senders : []).find((item) => item.phone === selectedSender.value);

  return String(sender?.waba_id || '');
});

const {
  templates,
  loading: templatesLoading,
  syncing: templatesSyncing,
  loaded: templatesLoaded,
  errorMsg: templatesError,
  staleWarning: templatesStale,
  loadTemplates,
  syncTemplates,
} = useWhatsappTemplates(api, { approvedOnly: true, wabaId: () => selectedSenderWaba.value });

const testMessageOpen = ref(false);
const testReceiverPhone = ref('');
const testMessageBody = ref(DEFAULT_TEST_MESSAGE);
const sendingTestMessage = ref(false);
const sendError = ref('');
const messageType = ref('text');
const templateKey = ref('');
const templateVariables = ref([]);
const windowState = ref('');
let windowTimer = null;
let windowRequest = 0;

const messageTypes = [
  { id: 'text', label: __('Text', textDomain) },
  { id: 'template', label: __('Template', textDomain) },
];

const senderOptions = computed(() =>
  (Array.isArray(props.senders) ? props.senders : []).map((sender) => ({
    value: sender.phone,
    label: sender.formatted || sender.phone,
    meta: sender.connection === 'connected' ? __('Connected', textDomain) : __('Disconnected', textDomain),
  }))
);

/**
 * Option value of a template: the same name can be approved in several languages.
 *
 * @since 2.8.0
 * @param {Object} template Template from the list.
 * @returns {string}
 */
function templateKeyOf(template) {
  return `${template.name}|${template.language || ''}`;
}

const templateOptions = computed(() =>
  templates.value.map((template) => ({
    value: templateKeyOf(template),
    label: template.language ? `${template.name} · ${template.language}` : template.name,
    meta: template.category || '',
  }))
);

const selectedTemplate = computed(() => templates.value.find((template) => templateKeyOf(template) === templateKey.value) || null);

// Meta refuses a template with an empty parameter, so the test waits for them.
const missingVariables = computed(() =>
  templateVariables.value.some((variable) => isRequiredSlot(variable) && !String(variable.value || '').trim())
);

const canSubmit = computed(() => {
  if (!props.sendTestMessage || !selectedSender.value || !testReceiverPhone.value) {
    return false;
  }

  if (messageType.value === 'template') {
    return Boolean(selectedTemplate.value) && !missingVariables.value;
  }

  return Boolean(testMessageBody.value.trim());
});

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
    messageType.value = 'text';
    sendError.value = '';
    scheduleWindowCheck();
    return;
  }

  sendingTestMessage.value = false;
  clearWindowCheck();
});

// The template list is only needed once someone picks "Template".
watch(messageType, (type) => {
  sendError.value = '';

  if (type === 'template' && !templatesLoaded.value && !templatesLoading.value) {
    loadTemplates();
  }
});

// A template is sent in the language it was approved in, with its own slots.
watch(selectedTemplate, (template) => {
  templateVariables.value = template ? buildVariableMap(template, templateVariables.value) : [];
});

// Another account has other templates: the choice is cleared and the list read again.
watch(selectedSenderWaba, (wabaId, previous) => {
  if (wabaId === previous) {
    return;
  }

  templateKey.value = '';

  if (messageType.value === 'template') {
    loadTemplates();
  } else {
    templatesLoaded.value = false;
  }
});

watch([selectedSender, testReceiverPhone], () => {
  if (testMessageOpen.value) {
    scheduleWindowCheck();
  }
});

onBeforeUnmount(clearWindowCheck);

/**
 * Stop a pending window check and forget the last answer.
 *
 * @since 2.8.0
 * @returns {void}
 */
function clearWindowCheck() {
  if (windowTimer) {
    clearTimeout(windowTimer);
    windowTimer = null;
  }

  windowRequest += 1;
  windowState.value = '';
}

/**
 * Ask for the window of the chosen recipient once the number settles.
 *
 * @since 2.8.0
 * @returns {void}
 */
function scheduleWindowCheck() {
  clearWindowCheck();

  const receiver = String(testReceiverPhone.value || '').replace(/\D/g, '');

  if (!api || !selectedSender.value || receiver.length < 8) {
    return;
  }

  windowTimer = setTimeout(() => checkWindow(receiver), WINDOW_CHECK_DELAY);
}

/**
 * Read the 24-hour window between the sender and the recipient.
 *
 * Only the latest request may answer, so a slow reply for a number that was
 * already changed is dropped.
 *
 * @since 2.8.0
 * @param {string} receiver Recipient number in digits.
 * @returns {Promise<void>}
 */
async function checkWindow(receiver) {
  const request = ++windowRequest;
  const sender = String(selectedSender.value || '').replace(/\D/g, '');

  try {
    const response = await api.get(`/admin/settings/phones/window?sender=${encodeURIComponent(sender)}&receiver=${encodeURIComponent(receiver)}`);

    if (request === windowRequest) {
      windowState.value = String(response?.window?.state || '');
    }
  } catch {
    if (request === windowRequest) {
      windowState.value = '';
    }
  }
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

/**
 * Send the test message as text or as the chosen template.
 *
 * The modal closes on success; a refusal stays on screen with its reason.
 *
 * @since 1.4.7
 * @version 2.8.0
 * @returns {Promise<void>}
 */
async function submitTestMessage() {
  if (sendingTestMessage.value || !canSubmit.value) {
    return;
  }

  sendingTestMessage.value = true;
  sendError.value = '';

  const payload = messageType.value === 'template'
    ? {
      type: 'template',
      sender: selectedSender.value,
      receiver: testReceiverPhone.value,
      template_name: selectedTemplate.value.name,
      language: selectedTemplate.value.language || '',
      variables: templateVariables.value,
    }
    : {
      type: 'text',
      sender: selectedSender.value,
      receiver: testReceiverPhone.value,
      message: testMessageBody.value.trim(),
    };

  try {
    const result = await props.sendTestMessage(payload);

    if (result?.ok) {
      testMessageOpen.value = false;
      return;
    }

    sendError.value = result?.message || __('Could not send the test message.', textDomain);
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
              {{ __('Send free text, or an approved template that also reaches contacts outside the 24-hour window.', textDomain) }}
            </p>
          </div>

          <div class="space-y-4">
            <BaseTabs v-model="messageType" :items="messageTypes" />

            <template v-if="messageType === 'text'">
              <div
                v-if="windowState === 'closed'"
                class="flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] leading-5 text-amber-800"
              >
                <span>{{ __('This contact has not messaged this number in the last 24 hours, so WhatsApp will refuse free text. Send an approved template instead.', textDomain) }}</span>
                <button
                  type="button"
                  class="shrink-0 rounded-[8px] border border-amber-300 bg-white px-3 py-1.5 text-[13px] font-semibold text-amber-800 transition hover:bg-amber-100"
                  @click="messageType = 'template'"
                >
                  {{ __('Send a template', textDomain) }}
                </button>
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
            </template>

            <template v-else>
              <div class="space-y-2">
                <SelectField
                  v-model="templateKey"
                  :field="{
                    label: __('Template', textDomain),
                    description: __('Only templates approved by Meta are listed. Each one is sent in the language it was approved in.', textDomain),
                    placeholder: templatesLoading ? __('Loading templates…', textDomain) : __('Select a template', textDomain),
                    emptyLabel: __('No approved template', textDomain),
                    searchable: true,
                    options: templateOptions,
                  }"
                  :disabled="templatesLoading"
                  name="test-message-template"
                />

                <div class="flex flex-wrap items-center gap-3">
                  <button
                    type="button"
                    class="text-xs font-medium text-slate-500 underline underline-offset-2 transition hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="templatesSyncing || templatesLoading"
                    @click="syncTemplates"
                  >
                    {{ templatesSyncing ? __('Syncing…', textDomain) : __('Sync now', textDomain) }}
                  </button>

                  <span v-if="templatesStale" class="text-xs text-amber-600">{{ templatesStale }}</span>
                </div>

                <p v-if="templatesError" class="text-xs text-rose-600">{{ templatesError }}</p>

                <p v-else-if="templatesLoaded && !templatesLoading && !templates.length" class="text-xs text-slate-500">
                  {{ __('No approved template found. Create one on the Joinotify panel and approve it with Meta first.', textDomain) }}
                </p>
              </div>

              <template v-if="selectedTemplate">
                <TemplatePreview :template="selectedTemplate" :variables="templateVariables" />

                <TemplateVariablesForm v-if="templateVariables.length" v-model:variables="templateVariables" />

                <p v-if="missingVariables" class="text-xs text-slate-500">
                  {{ __('Fill in every variable to send the template. Meta refuses it with an empty value.', textDomain) }}
                </p>
              </template>
            </template>
          </div>
        </div>

        <p
          v-if="sendError"
          class="rounded-[8px] border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] leading-5 text-rose-700"
          role="alert"
        >
          {{ sendError }}
        </p>

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
            :disabled="!canSubmit"
            @click="submitTestMessage"
          />
        </div>
      </div>
    </ModalDialog>
  </div>
</template>
