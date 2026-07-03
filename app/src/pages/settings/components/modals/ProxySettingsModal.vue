<script setup>

/**
 * ProxySettingsModal.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import Tooltip from '../../../../components/tooltips/Tooltip.vue';
import InputGroupField from '../../../../components/fields/InputGroupField.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['close', 'update-setting', 'reset-field', 'generate-key']);

async function copyApiKey() {
  const value = String(props.settings.proxy_api_key || '').trim();

  if (!value) {
    return;
  }

  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value);
    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', 'true');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}
</script>

<template>
  <ModalDialog :open="open" :title="__('Configure Proxy API', textDomain)" :description="__('Adjust the routes and API key used by the proxy.', textDomain)" :eyebrow="__('General', textDomain)" size-class="max-w-4xl" @close="$emit('close')">
    <div class="space-y-5">
      <div class="grid gap-4 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <div>
          <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Text message route', textDomain) }}</h4>
          <p class="mt-1 text-[13px] leading-5 text-slate-500">
            {{ __('Defines the endpoint used to send text messages through the Proxy API.', textDomain) }}
          </p>
        </div>

        <InputGroupField
          name="send_text_proxy_api_route"
          :model-value="settings.send_text_proxy_api_route"
          :placeholder="__('send-message/text', textDomain)"
          @update:modelValue="$emit('update-setting', 'send_text_proxy_api_route', $event)"
        >
          <template #actions>
            <Tooltip :content="__('Restore default', textDomain)" placement="left">
              <button
                type="button"
                class="inline-flex h-full min-h-[48px] w-11 items-center justify-center border-0 border-l border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                :aria-label="__('Restore default', textDomain)"
                @click="$emit('reset-field', 'send_text_proxy_api_route')"
              >
                <span class="sr-only">{{ __('Restore default', textDomain) }}</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M3 12a9 9 0 1 1 3.1 6.8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M3 4v8h8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </Tooltip>
          </template>
        </InputGroupField>
      </div>

      <div class="grid gap-4 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <div>
          <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Media message route', textDomain) }}</h4>
          <p class="mt-1 text-[13px] leading-5 text-slate-500">
            {{ __('Defines the endpoint used to send media through the Proxy API.', textDomain) }}
          </p>
        </div>

        <InputGroupField
          name="send_media_proxy_api_route"
          :model-value="settings.send_media_proxy_api_route"
          :placeholder="__('send-message/media', textDomain)"
          @update:modelValue="$emit('update-setting', 'send_media_proxy_api_route', $event)"
        >
          <template #actions>
            <Tooltip :content="__('Restore default', textDomain)" placement="left">
              <button
                type="button"
                class="inline-flex h-full min-h-[48px] w-11 items-center justify-center border-0 border-l border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                :aria-label="__('Restore default', textDomain)"
                @click="$emit('reset-field', 'send_media_proxy_api_route')"
              >
                <span class="sr-only">{{ __('Restore default', textDomain) }}</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M3 12a9 9 0 1 1 3.1 6.8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M3 4v8h8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </Tooltip>
          </template>
        </InputGroupField>
      </div>

      <div class="grid gap-4 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <div>
          <h4 class="text-[15px] font-semibold text-slate-800">{{ __('API key', textDomain) }}</h4>
          <p class="mt-1 text-[13px] leading-5 text-slate-500">
            {{ __('Generate a new key to authenticate Proxy API calls.', textDomain) }}
          </p>
        </div>

        <InputGroupField
          name="proxy_api_key"
          :model-value="settings.proxy_api_key"
          :placeholder="''"
          @update:modelValue="$emit('update-setting', 'proxy_api_key', $event)"
        >
          <template #actions>
            <Tooltip :content="__('Copy code', textDomain)" placement="left">
              <button
                type="button"
                class="inline-flex h-full min-h-[48px] w-11 items-center justify-center border-0 border-l border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                :aria-label="__('Copy code', textDomain)"
                @click="copyApiKey"
              >
                <span class="sr-only">{{ __('Copy code', textDomain) }}</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M9 9V5.6C9 4.72 9.72 4 10.6 4H19.4C20.28 4 21 4.72 21 5.6V14.4C21 15.28 20.28 16 19.4 16H16"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M4.6 8H13.4C14.28 8 15 8.72 15 9.6V18.4C15 19.28 14.28 20 13.4 20H4.6C3.72 20 3 19.28 3 18.4V9.6C3 8.72 3.72 8 4.6 8Z"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </Tooltip>

            <Tooltip :content="__('Generate a new key', textDomain)" placement="left">
              <button
                type="button"
                class="inline-flex h-full min-h-[48px] w-11 items-center justify-center border-0 border-l border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                :aria-label="__('Generate a new key', textDomain)"
                @click="$emit('generate-key')"
              >
                <span class="sr-only">{{ __('Generate a new key', textDomain) }}</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M21 12a9 9 0 1 1-3.2-6.8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M21 4v8h-8"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </Tooltip>
          </template>
        </InputGroupField>
      </div>
    </div>
  </ModalDialog>
</template>
