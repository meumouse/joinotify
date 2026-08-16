<script setup>

/**
 * CloudConnectField.vue frontend component.
 *
 * Connects the site to a Joinotify account without the user ever handling a
 * token: the panel is opened in a new tab, the account is authorized there
 * (running Meta's Embedded Signup when needed) and the browser comes back to
 * this page with a single-use code, which the backend trades for an API key
 * server to server.
 *
 * The field is bound to the API token setting, so pasting a key by hand stays
 * available as a fallback and writes to exactly the same place.
 *
 * @since 2.3.0
 */
import { computed, inject, onMounted, ref } from 'vue';
import TextField from './TextField.vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const api = inject('joinotifyApi', null);

const loading = ref(false);
const errorMsg = ref('');
const successMsg = ref('');
const manualOpen = ref(false);

const token = computed(() => String(props.modelValue || '').trim());
const connected = computed(() => token.value !== '');

// The first 14 characters are the public key prefix; the secret never needs to
// be read back to know which key is in use.
const tokenPrefix = computed(() => (connected.value ? `${token.value.slice(0, 14)}…` : ''));

const panelUrl = computed(() => {
  const configured = props.field?.component_props?.panel_url;
  return typeof configured === 'string' && configured.trim() ? configured.trim() : 'https://app.joinotify.com';
});

/**
 * Strip the handshake parameters from the address bar so a reload does not try
 * to spend an already consumed code.
 *
 * @since 2.3.0
 * @returns {void}
 */
function cleanUrl() {
  if (typeof window === 'undefined' || !window.history?.replaceState) {
    return;
  }

  const url = new URL(window.location.href);

  url.searchParams.delete('code');
  url.searchParams.delete('state');

  window.history.replaceState({}, '', url.toString());
}

/**
 * Open the panel to start a handshake.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function connect() {
  if (!api || loading.value) {
    return;
  }

  loading.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const res = await api.post('admin/cloud/connect/start', {});

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Could not start the connection.', textDomain);
      return;
    }

    if (res?.url && typeof window !== 'undefined') {
      window.location.assign(res.url);
    }
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}

/**
 * Trade the code the panel handed back for an API key.
 *
 * @since 2.3.0
 * @param {string} code Single-use code from the panel.
 * @param {string} state State echoed back by the panel.
 * @returns {Promise<void>}
 */
async function finish(code, state) {
  if (!api) {
    return;
  }

  loading.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const res = await api.post('admin/cloud/connect/finish', { code, state });

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Could not finish the connection.', textDomain);
      return;
    }

    if (res?.token) {
      emit('update:modelValue', res.token);
    }

    const notes = [res?.sync_error, res?.webhook_error].filter(Boolean).join(' ');

    successMsg.value = [res?.message || __('Your Joinotify account is connected.', textDomain), notes]
      .filter(Boolean)
      .join(' ');
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}

/**
 * Forget the stored key. The numbers stay untouched until the next sync.
 *
 * @since 2.3.0
 * @returns {void}
 */
function disconnect() {
  emit('update:modelValue', '');
  successMsg.value = '';
  errorMsg.value = '';
  manualOpen.value = true;
}

onMounted(() => {
  if (typeof window === 'undefined') {
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const code = params.get('code');
  const state = params.get('state');

  if (code && state) {
    cleanUrl();
    finish(code, state);
  }
});
</script>

<template>
  <div class="space-y-3">
    <div
      class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border px-4 py-3"
      :class="connected ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'"
    >
      <div class="min-w-0">
        <p class="text-[13px] font-semibold" :class="connected ? 'text-emerald-800' : 'text-slate-700'">
          {{ connected ? __('Account connected', textDomain) : __('Account not connected', textDomain) }}
        </p>

        <p class="mt-0.5 text-xs text-slate-600">
          <template v-if="connected">{{ tokenPrefix }}</template>
          <template v-else>
            {{ __('Connect your Joinotify account to send through the official WhatsApp Cloud API.', textDomain) }}
          </template>
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <button
          v-if="!connected"
          type="button"
          class="inline-flex items-center rounded-[8px] bg-slate-900 px-3 py-2 text-[13px] font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="disabled || loading || !api"
          @click="connect"
        >
          {{ loading ? __('Connecting…', textDomain) : __('Connect to Joinotify', textDomain) }}
        </button>

        <template v-else>
          <a
            class="inline-flex items-center rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
            :href="panelUrl"
            target="_blank"
            rel="noopener noreferrer"
          >{{ __('Open panel', textDomain) }}</a>

          <button
            type="button"
            class="inline-flex items-center rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-600 transition hover:border-rose-200 hover:text-rose-600"
            :disabled="disabled || loading"
            @click="disconnect"
          >{{ __('Disconnect', textDomain) }}</button>
        </template>
      </div>
    </div>

    <p v-if="successMsg" class="text-xs text-emerald-700">{{ successMsg }}</p>
    <p v-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>

    <div>
      <button
        type="button"
        class="text-xs font-medium text-slate-500 underline underline-offset-2 transition hover:text-slate-700"
        @click="manualOpen = !manualOpen"
      >
        {{ manualOpen ? __('Hide manual token', textDomain) : __('Enter a token manually', textDomain) }}
      </button>

      <div v-if="manualOpen" class="mt-2">
        <TextField
          :field="field"
          :name="name"
          :disabled="disabled"
          :model-value="modelValue"
          @update:model-value="emit('update:modelValue', $event)"
        />
      </div>
    </div>
  </div>
</template>
