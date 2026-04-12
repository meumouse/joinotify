<script setup>
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import Tooltip from '../../../../components/tooltips/Tooltip.vue';

defineProps({
  open: { type: Boolean, default: false },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['close', 'update-setting', 'reset-field', 'generate-key']);
</script>

<template>
  <ModalDialog :open="open" :title="__('Configure Proxy API', textDomain)" :description="__('Adjust the routes and API key used by the proxy.', textDomain)" :eyebrow="__('General', textDomain)" size-class="max-w-4xl" @close="$emit('close')">
    <div class="space-y-5">
      <div class="grid gap-4 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <div>
          <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Text message route', textDomain) }}</h4>
          <p class="mt-1 text-[13px] leading-5 text-slate-500">
            {{ __('Defines the endpoint used to send text messages through the Proxy API.', textDomain) }}
          </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <input
            :value="settings.send_text_proxy_api_route"
            type="text"
            class="w-full rounded-[0.375rem] border border-slate-200 bg-white px-4 py-3.5 text-[15px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            :placeholder="__('send-message/text', textDomain)"
            @input="$emit('update-setting', 'send_text_proxy_api_route', $event.target.value)"
          />
          <Tooltip :content="__('Restore default', textDomain)" placement="top">
            <button
              type="button"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.375rem] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
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
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <div>
          <h4 class="text-[15px] font-semibold text-slate-800">{{ __('Media message route', textDomain) }}</h4>
          <p class="mt-1 text-[13px] leading-5 text-slate-500">
            {{ __('Defines the endpoint used to send media through the Proxy API.', textDomain) }}
          </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <input
            :value="settings.send_media_proxy_api_route"
            type="text"
            class="w-full rounded-[0.375rem] border border-slate-200 bg-white px-4 py-3.5 text-[15px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            :placeholder="__('send-message/media', textDomain)"
            @input="$emit('update-setting', 'send_media_proxy_api_route', $event.target.value)"
          />
          <Tooltip :content="__('Restore default', textDomain)" placement="top">
            <button
              type="button"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.375rem] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
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
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <div>
          <h4 class="text-[15px] font-semibold text-slate-800">{{ __('API key', textDomain) }}</h4>
          <p class="mt-1 text-[13px] leading-5 text-slate-500">
            {{ __('Generate a new key to authenticate Proxy API calls.', textDomain) }}
          </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
          <input
            :value="settings.proxy_api_key"
            type="text"
            class="w-full rounded-[0.375rem] border border-slate-200 bg-white px-4 py-3.5 text-[15px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
            :placeholder="''"
            @input="$emit('update-setting', 'proxy_api_key', $event.target.value)"
          />
          <Tooltip :content="__('Generate a new key', textDomain)" placement="top">
            <button
              type="button"
              class="inline-flex h-11 items-center gap-2 rounded-[0.375rem] bg-primary-700 px-4 text-[14px] font-semibold text-white transition hover:bg-primary-800"
              :aria-label="__('Generate a new key', textDomain)"
              @click="$emit('generate-key')"
            >
              <span>{{ __('Generate key', textDomain) }}</span>
            </button>
          </Tooltip>
        </div>
      </div>
    </div>
  </ModalDialog>
</template>
