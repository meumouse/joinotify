<script setup>

/**
 * JoinotifyApiKeyField.vue frontend component.
 *
 * Takes the API key issued for this site on the Joinotify panel and connects
 * with it. Pasting a key is not the whole job — the key has to be proven
 * against the API and the account's numbers imported before the site can send
 * anything — so this control performs the exchange itself instead of leaving a
 * string in the form for the Save button to store unverified.
 *
 * The stored key is never read back into the browser: the backend reports only
 * whether one exists and its public prefix.
 *
 * @since 2.3.0
 */
import { computed, inject, ref } from 'vue';
import { __, _n, sprintf, textDomain } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  panelUrl: { type: String, default: 'https://app.joinotify.com' },
  apiHost: { type: String, default: 'api.joinotify.com' },
  connected: { type: Boolean, default: false },
  keyPrefix: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const api = inject('joinotifyApi', null);

const apiKey = ref('');
const loading = ref(false);
const errorMsg = ref('');
const successMsg = ref('');
// Seeded by the backend, then owned by this component once the user acts. The
// stored key never travels to the browser, so all it knows is whether one
// exists and its public prefix.
const connection = ref({ connected: props.connected, key_prefix: props.keyPrefix });
const accountName = ref('');
const confirmingRemoval = ref(false);

const isConnected = computed(() => connection.value.connected);

// The leading `sk_live_`/`sk_test_` segment is public and identifies which key
// is in use; the secret half is stood in for so the field reads as a filled
// credential rather than an empty box.
const maskedKey = computed(() => {
  const prefix = String(connection.value.key_prefix || '').trim();

  return prefix ? `${prefix}${'•'.repeat(24)}` : '•'.repeat(32);
});

/**
 * Send the key to the backend, which validates it and imports the numbers.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function connect() {
  if (!api || loading.value) {
    return;
  }

  const key = apiKey.value.trim();

  if (!key) {
    errorMsg.value = __('Paste the API key issued for this site on the Joinotify panel.', textDomain);
    return;
  }

  loading.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const response = await api.post('/admin/cloud/connect/key', { api_key: key });

    if (response?.status === 'error') {
      errorMsg.value = response.message || __('Could not connect with that key.', textDomain);
      return;
    }

    connection.value = response.connection || { connected: true, key_prefix: key.slice(0, 14) };
    accountName.value = response.account || '';
    apiKey.value = '';

    // Keep the form in step with what the backend just stored, so saving the
    // settings afterwards does not write the pre-connect value back over it.
    emit('update:modelValue', key);

    const imported = sprintf(
      _n('%d number imported.', '%d numbers imported.', response.sender_count || 0, textDomain),
      response.sender_count || 0
    );

    successMsg.value = [response.message, imported, response.webhook_error].filter(Boolean).join(' ');
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}

/**
 * Forget the stored key. The imported numbers are left untouched.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function disconnect() {
  if (!api || loading.value) {
    return;
  }

  loading.value = true;
  errorMsg.value = '';
  successMsg.value = '';
  confirmingRemoval.value = false;

  try {
    const response = await api.post('/admin/cloud/connect/key', { disconnect: true });

    if (response?.status === 'error') {
      errorMsg.value = response.message || __('Could not disconnect.', textDomain);
      return;
    }

    connection.value = response.connection || { connected: false, key_prefix: '' };
    accountName.value = '';
    successMsg.value = response.message || '';
    emit('update:modelValue', '');
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="space-y-3">
    <div v-if="isConnected" class="space-y-3">
      <div class="flex flex-wrap items-center gap-2">
        <input
          :value="maskedKey"
          type="text"
          readonly
          :aria-label="__('Saved Joinotify API key', textDomain)"
          class="min-w-0 flex-1 cursor-default rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 font-mono text-[13px] tracking-tight text-emerald-900 outline-none"
        >

        <button
          v-if="!confirmingRemoval"
          type="button"
          class="inline-flex shrink-0 items-center rounded-[8px] border border-slate-200 bg-white px-3 py-3 text-[13px] font-medium text-slate-600 transition hover:border-rose-200 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="disabled || loading"
          @click="confirmingRemoval = true"
        >
          {{ __('Remove key', textDomain) }}
        </button>

        <template v-else>
          <button
            type="button"
            class="inline-flex shrink-0 items-center rounded-[8px] bg-danger px-3 py-3 text-[13px] font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disabled || loading"
            @click="disconnect"
          >
            {{ loading ? __('Removing…', textDomain) : __('Confirm', textDomain) }}
          </button>

          <button
            type="button"
            class="inline-flex shrink-0 items-center rounded-[8px] border border-slate-200 bg-white px-3 py-3 text-[13px] font-medium text-slate-600 transition hover:bg-slate-50"
            :disabled="loading"
            @click="confirmingRemoval = false"
          >
            {{ __('Cancel', textDomain) }}
          </button>
        </template>
      </div>

      <p class="text-xs leading-5 text-slate-500">
        <span class="font-semibold text-emerald-700">{{ __('Connected', textDomain) }}</span>
        <template v-if="accountName"> — {{ accountName }}</template>
        &middot;
        <template v-if="confirmingRemoval">
          {{ __('Removing the key stops all message delivery from this site. The numbers already imported are kept.', textDomain) }}
        </template>
        <template v-else>
          {{ __('Only the public prefix of your key is shown. Remove it to connect a different account.', textDomain) }}
        </template>
      </p>
    </div>

    <div v-else class="space-y-3">
      <div class="rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-3">
        <p class="text-[13px] font-semibold text-slate-700">{{ __('Account not connected', textDomain) }}</p>
        <p class="mt-0.5 text-xs text-slate-600">
          {{ __('Messages cannot be delivered until this site is connected.', textDomain) }}
        </p>
      </div>

      <input
        v-model="apiKey"
        type="password"
        autocomplete="off"
        spellcheck="false"
        :placeholder="field.placeholder || 'sk_live_...'"
        :disabled="disabled || loading"
        class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
        @keyup.enter="connect"
      >

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="button"
          class="inline-flex items-center rounded-[8px] bg-primary-700 px-4 py-2.5 text-[13px] font-medium text-white transition hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="disabled || loading || !api"
          @click="connect"
        >
          {{ loading ? __('Connecting…', textDomain) : __('Connect', textDomain) }}
        </button>

        <a
          class="text-[13px] font-semibold text-primary-700 underline underline-offset-4"
          :href="panelUrl"
          target="_blank"
          rel="noopener noreferrer"
        >{{ __('Get a key on the Joinotify panel', textDomain) }}</a>
      </div>

      <p class="text-xs leading-5 text-slate-500">
        {{ sprintf(__('Connecting sends your key and this site’s address to %s so it can register the site and list your WhatsApp numbers.', textDomain), apiHost) }}
      </p>
    </div>

    <p v-if="successMsg" class="text-xs leading-5 text-emerald-700" role="status">{{ successMsg }}</p>
    <p v-if="errorMsg" class="text-xs leading-5 text-rose-600" role="alert">{{ errorMsg }}</p>
  </div>
</template>
