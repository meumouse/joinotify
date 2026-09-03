<script setup>

/**
 * ConnectStep.vue frontend component.
 *
 * Takes the API key issued for this site on the Joinotify panel, hands it to
 * the backend for validation, and reports which numbers came back. This is the
 * first moment the site contacts an external server, so the step says so in
 * plain words before the user acts.
 *
 * @since 2.3.0
 */
import { computed, ref } from 'vue';
import { __, _n, sprintf, textDomain } from '../../../../utils/i18n';
import BaseButton from '../../../../components/base/BaseButton.vue';
import StatusBadge from '../../../../components/cards/StatusBadge.vue';

const props = defineProps({
  api: { type: Object, default: null },
  connected: { type: Boolean, default: false },
  panelUrl: { type: String, default: '' },
  apiHost: { type: String, default: 'api.joinotify.com' },
});

const emit = defineEmits(['connected']);

const apiKey = ref('');
const loading = ref(false);
const errorMsg = ref('');
const result = ref(null);

const isConnected = computed(() => props.connected || Boolean(result.value));

const senderSummary = computed(() => {
  const count = result.value?.sender_count || 0;

  return sprintf(
    _n('%d number imported from your Joinotify account.', '%d numbers imported from your Joinotify account.', count, textDomain),
    count
  );
});

/**
 * Validate and store the pasted key.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function connect() {
  if (!props.api || loading.value) {
    return;
  }

  const key = apiKey.value.trim();

  if (!key) {
    errorMsg.value = __('Paste the API key issued for this site on the Joinotify panel.', textDomain);
    return;
  }

  loading.value = true;
  errorMsg.value = '';

  try {
    const response = await props.api.post('/admin/onboarding/connect', { api_key: key });

    if (response?.status === 'error') {
      errorMsg.value = response.message || __('Could not connect with that key.', textDomain);
      return;
    }

    result.value = response;
    apiKey.value = '';
    emit('connected', response);
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('Joinotify delivers your messages through the official WhatsApp Cloud API. Create a key for this site on the Joinotify panel and paste it below — it is the only credential the plugin needs.', textDomain) }}
    </p>

    <div
      v-if="isConnected"
      class="max-w-2xl rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3"
      role="status"
    >
      <div class="flex flex-wrap items-center gap-3">
        <StatusBadge :label="__('Connected', textDomain)" tone="success" />
        <span v-if="result?.account" class="text-[14px] font-semibold text-emerald-900">{{ result.account }}</span>
      </div>

      <p v-if="result" class="mt-2 text-[13px] leading-5 text-emerald-800">{{ senderSummary }}</p>

      <p v-if="result?.webhook_error" class="mt-2 text-[13px] leading-5 text-amber-800">
        {{ __('Messages will send normally, but delivery reports could not be set up:', textDomain) }}
        {{ result.webhook_error }}
      </p>
    </div>

    <div v-else class="max-w-2xl space-y-4">
      <div>
        <label class="mb-2 block text-[14px] font-medium text-slate-700" for="joinotify-onboarding-key">
          {{ __('Joinotify API key', textDomain) }}
        </label>

        <input
          id="joinotify-onboarding-key"
          v-model="apiKey"
          type="password"
          autocomplete="off"
          spellcheck="false"
          placeholder="sk_live_..."
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10"
          @keyup.enter="connect"
        >
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <BaseButton
          :title="loading ? __('Connecting…', textDomain) : __('Connect', textDomain)"
          :loading="loading"
          :disabled="!api"
          @click="connect"
        />

        <a
          v-if="panelUrl"
          class="text-[13px] font-semibold text-primary-700 underline underline-offset-4"
          :href="panelUrl"
          target="_blank"
          rel="noopener noreferrer"
        >{{ __('Open the Joinotify panel to get a key', textDomain) }}</a>
      </div>

      <p v-if="errorMsg" class="text-[13px] leading-5 text-rose-600" role="alert">{{ errorMsg }}</p>
    </div>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
        {{ __('What this sends', textDomain) }}
      </p>
      <p class="mt-2 text-[13px] leading-5 text-slate-600">
        {{ sprintf(__('Connecting sends your key and this site’s address to %s so it can register the site and list the WhatsApp numbers on your account. Nothing leaves this site before you press Connect.', textDomain), apiHost) }}
      </p>
    </div>
  </div>
</template>
