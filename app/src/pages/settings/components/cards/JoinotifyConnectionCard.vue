<script setup>

/**
 * JoinotifyConnectionCard.vue frontend component.
 *
 * The connection between this site and a Joinotify account, at the top of
 * Settings → General. Joinotify sends every WhatsApp message through that
 * account, so this card replaced the WhatsApp card of the integrations tab.
 *
 * Four states:
 * - disconnected: connect through the panel (the browser comes back here with
 *   a single-use code the backend trades for a key), or paste a key;
 * - connected: account, key prefix, numbers and last sync, with the actions
 *   to sync, open the panel, manage the numbers and disconnect;
 * - locked: the key is defined in wp-config.php, so there is no disconnect;
 * - error: the API refused the key the last time it was asked.
 *
 * The key never reaches the browser: the card only knows whether one is
 * stored and its public prefix.
 *
 * @since 2.8.0
 */
import { computed, inject, onMounted, ref } from 'vue';
import { __, _n, sprintf, textDomain } from '../../../../utils/i18n';
import BaseButton from '../../../../components/base/BaseButton.vue';

const props = defineProps({
  connection: { type: Object, default: () => ({}) },
  phones: { type: Object, default: () => ({ senders: [], sender_count: 0 }) },
  settings: { type: Object, default: () => ({}) },
  syncing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:connection', 'update:phones', 'update-setting', 'sync', 'manage-numbers', 'notify']);

const api = inject('joinotifyApi', null);

const loading = ref('');
const errorMessage = ref('');
const keyFormOpen = ref(false);
const apiKey = ref('');
const confirmingDisconnect = ref(false);
const editingIds = ref(false);

// Modes in which the API refused the key: the site looks connected but cannot send.
const REFUSED_MODES = ['unauthorized', 'blocked', 'forbidden'];

const isConnected = computed(() => Boolean(props.connection?.connected));
const isLocked = computed(() => isConnected.value && Boolean(props.connection?.locked));
const isRefused = computed(() => isConnected.value && REFUSED_MODES.includes(String(props.connection?.mode || '')));
const state = computed(() => {
  if (!isConnected.value) return 'disconnected';
  if (isRefused.value) return 'error';
  if (isLocked.value) return 'locked';
  return 'connected';
});

const panelUrl = computed(() => String(props.connection?.panel_url || props.phones?.panel_url || 'https://app.joinotify.com'));
const apiHost = computed(() => String(props.connection?.api_host || 'api.joinotify.com'));
const accountName = computed(() => String(props.connection?.account || '').trim());
const maskedKey = computed(() => {
  const prefix = String(props.connection?.key_prefix || '').trim();

  return prefix ? `${prefix}${'•'.repeat(12)}` : '';
});
const senders = computed(() => (Array.isArray(props.phones?.senders) ? props.phones.senders : []));
const senderCount = computed(() => Number(props.phones?.sender_count ?? senders.value.length) || 0);
const lastSyncLabel = computed(() => {
  const value = Number(props.phones?.last_sync || 0);

  return value ? new Date(value * 1000).toLocaleString() : '';
});

const statusLabel = computed(() => {
  switch (state.value) {
    case 'error':
      return __('Connection needs attention', textDomain);
    case 'locked':
    case 'connected':
      return __('Connected', textDomain);
    default:
      return __('Not connected', textDomain);
  }
});

const errorText = computed(() => {
  const message = String(props.connection?.mode_message || '').trim();

  if (message) {
    return message;
  }

  if (props.connection?.mode === 'blocked') {
    return __('Your Joinotify account is blocked for sending. Check your plan on the Joinotify panel.', textDomain);
  }

  return __('The Joinotify API refused the key of this site. It may have been revoked on the panel. Connect again to issue a new one.', textDomain);
});

/**
 * Colour a quality pill the way Meta reports the rating.
 *
 * @since 2.8.0
 * @param {string} rating Quality rating reported by Meta.
 * @returns {string} Tailwind classes.
 */
function qualityClass(rating) {
  switch (String(rating || '').toUpperCase()) {
    case 'GREEN':
      return 'bg-emerald-100 text-emerald-700';
    case 'YELLOW':
      return 'bg-amber-100 text-amber-700';
    case 'RED':
      return 'bg-rose-100 text-rose-700';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

/**
 * Hand a connection answer to the page: state, numbers and ids.
 *
 * @since 2.8.0
 * @param {Object} response REST answer carrying `connection` and `phones`.
 * @returns {void}
 */
function applyResponse(response) {
  if (response?.connection) {
    emit('update:connection', response.connection);
  }

  if (response?.phones) {
    emit('update:phones', response.phones);
  }
}

/**
 * Strip the handshake parameters from the address bar so a reload does not
 * try to spend an already consumed code.
 *
 * @since 2.8.0
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
 * Send the user to the panel to authorize this site.
 *
 * @since 2.8.0
 * @returns {Promise<void>}
 */
async function startHandshake() {
  if (!api || loading.value) {
    return;
  }

  loading.value = 'handshake';
  errorMessage.value = '';

  try {
    const response = await api.post('/admin/cloud/connect/start', {});

    if (response?.status === 'error' || !response?.url) {
      errorMessage.value = response?.message || __('Could not start the connection.', textDomain);
      loading.value = '';
      return;
    }

    // Leaving the page: the loading state stays on until it unloads.
    window.location.assign(response.url);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
    loading.value = '';
  }
}

/**
 * Trade the code the panel handed back for an API key.
 *
 * @since 2.8.0
 * @param {string} code Single-use code from the panel.
 * @param {string} handshakeState State echoed back by the panel.
 * @returns {Promise<void>}
 */
async function finishHandshake(code, handshakeState) {
  if (!api) {
    return;
  }

  loading.value = 'handshake';
  errorMessage.value = '';

  try {
    const response = await api.post('/admin/cloud/connect/finish', { code, state: handshakeState });

    if (response?.status === 'error') {
      errorMessage.value = response.message || __('Could not finish the connection.', textDomain);
      return;
    }

    applyResponse(response);

    const notes = [response?.sync_error, response?.webhook_error].filter(Boolean).join(' ');

    emit('notify', [response?.message || __('Your Joinotify account is connected.', textDomain), notes].filter(Boolean).join(' '), notes ? 'warning' : 'success');
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = '';
  }
}

/**
 * Connect with a key pasted by hand. The backend proves it against the API
 * and imports the numbers before storing it.
 *
 * @since 2.8.0
 * @returns {Promise<void>}
 */
async function connectWithKey() {
  if (!api || loading.value) {
    return;
  }

  const key = apiKey.value.trim();

  if (!key) {
    errorMessage.value = __('Paste the API key issued for this site on the Joinotify panel.', textDomain);
    return;
  }

  loading.value = 'key';
  errorMessage.value = '';

  try {
    const response = await api.post('/admin/cloud/connect/key', { api_key: key });

    if (response?.status === 'error') {
      errorMessage.value = response.message || __('Could not connect with that key.', textDomain);
      return;
    }

    apiKey.value = '';
    keyFormOpen.value = false;
    applyResponse(response);

    const imported = sprintf(
      _n('%d number imported.', '%d numbers imported.', response?.sender_count || 0, textDomain),
      response?.sender_count || 0
    );

    emit('notify', [response?.message, imported, response?.webhook_error].filter(Boolean).join(' '), response?.webhook_error ? 'warning' : 'success');
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = '';
  }
}

/**
 * Forget the stored key. The imported numbers are kept.
 *
 * @since 2.8.0
 * @returns {Promise<void>}
 */
async function disconnect() {
  if (!api || loading.value) {
    return;
  }

  loading.value = 'disconnect';
  errorMessage.value = '';

  try {
    const response = await api.post('/admin/cloud/connect/key', { disconnect: true });

    if (response?.status === 'error') {
      errorMessage.value = response.message || __('Could not disconnect.', textDomain);
      return;
    }

    confirmingDisconnect.value = false;
    editingIds.value = false;
    applyResponse(response);
    emit('notify', response?.message || __('This site is no longer connected to Joinotify.', textDomain), 'info');
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = '';
  }
}

onMounted(() => {
  if (typeof window === 'undefined') {
    return;
  }

  // Back from the panel: the address carries the code to trade.
  const params = new URLSearchParams(window.location.search);
  const code = params.get('code');
  const handshakeState = params.get('state');

  if (code && handshakeState) {
    cleanUrl();
    void finishHandshake(code, handshakeState);
  }
});
</script>

<template>
  <section
    class="rounded-xl border p-6 lg:p-8"
    :class="{
      'border-primary-200 bg-gradient-to-br from-primary-50 via-white to-white': state === 'disconnected',
      'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-white': state === 'connected' || state === 'locked',
      'border-rose-200 bg-gradient-to-br from-rose-50 via-white to-white': state === 'error',
    }"
    :aria-busy="loading === 'handshake' ? 'true' : 'false'"
  >
    <div class="flex flex-wrap items-start justify-between gap-6">
      <div class="min-w-0 max-w-2xl">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex h-2.5 w-2.5 rounded-full"
            :class="{
              'bg-slate-400': state === 'disconnected',
              'bg-emerald-500': state === 'connected' || state === 'locked',
              'bg-rose-500': state === 'error',
            }"
            aria-hidden="true"
          />
          <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ statusLabel }}</span>
        </div>

        <h3 class="mt-2 text-xl font-semibold text-slate-800">
          <template v-if="state === 'disconnected'">{{ __('Connect this site to Joinotify', textDomain) }}</template>
          <template v-else>{{ accountName || __('Joinotify account', textDomain) }}</template>
        </h3>

        <p v-if="state === 'disconnected'" class="mt-2 text-[14px] leading-6 text-slate-600">
          {{ __('Joinotify sends your WhatsApp messages through the official WhatsApp Cloud API. Connect your account to import your numbers and start sending.', textDomain) }}
        </p>

        <p v-else-if="state === 'error'" class="mt-2 text-[14px] leading-6 text-rose-700" role="alert">
          {{ errorText }}
        </p>

        <dl v-if="state !== 'disconnected'" class="mt-4 grid gap-x-8 gap-y-3 text-[13px] sm:grid-cols-3">
          <div v-if="maskedKey">
            <dt class="text-slate-500">{{ __('API key', textDomain) }}</dt>
            <dd class="mt-0.5 font-mono text-slate-800">{{ maskedKey }}</dd>
          </div>
          <div>
            <dt class="text-slate-500">{{ __('Numbers', textDomain) }}</dt>
            <dd class="mt-0.5 font-semibold text-slate-800">
              {{ sprintf(_n('%d number', '%d numbers', senderCount, textDomain), senderCount) }}
            </dd>
          </div>
          <div>
            <dt class="text-slate-500">{{ __('Last synced', textDomain) }}</dt>
            <dd class="mt-0.5 text-slate-800">{{ lastSyncLabel || __('Never', textDomain) }}</dd>
          </div>
        </dl>

        <ul v-if="state !== 'disconnected' && senders.length" class="mt-4 flex flex-wrap gap-2">
          <li
            v-for="sender in senders.slice(0, 4)"
            :key="sender.phone"
            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[12px] text-slate-700"
          >
            <span>{{ sender.verified_name || sender.formatted || sender.phone }}</span>
            <span
              v-if="sender.quality_rating"
              class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
              :class="qualityClass(sender.quality_rating)"
            >{{ sender.quality_rating }}</span>
          </li>
          <li v-if="senders.length > 4" class="inline-flex items-center px-1 text-[12px] text-slate-500">
            {{ sprintf(__('+%d more', textDomain), senders.length - 4) }}
          </li>
        </ul>

        <p v-if="state === 'locked'" class="mt-4 text-xs leading-5 text-slate-500">
          {{ __('This key is defined in wp-config.php (JOINOTIFY_CLOUD_API_TOKEN) and can only be changed there.', textDomain) }}
        </p>
      </div>

      <!-- Actions -->
      <div v-if="state === 'disconnected'" class="flex shrink-0 flex-col items-stretch gap-3 sm:min-w-[240px]">
        <BaseButton
          :title="__('Connect with Joinotify', textDomain)"
          :loading="loading === 'handshake'"
          :disabled="Boolean(loading) || !api"
          @click="startHandshake"
        />
        <button
          type="button"
          class="text-[13px] font-medium text-primary-700 underline underline-offset-4"
          :aria-expanded="keyFormOpen ? 'true' : 'false'"
          @click="keyFormOpen = !keyFormOpen"
        >
          {{ __('I already have an API key', textDomain) }}
        </button>
      </div>

      <div v-else class="flex shrink-0 flex-wrap items-center gap-2">
        <BaseButton
          v-if="state === 'error'"
          :title="__('Connect again', textDomain)"
          size="sm"
          :loading="loading === 'handshake'"
          :disabled="Boolean(loading) || !api"
          @click="startHandshake"
        />
        <BaseButton
          v-if="state === 'error' && connection.action_url"
          :title="__('Fix on the panel', textDomain)"
          size="sm"
          variant="secondary"
          :href="connection.action_url"
          target="_blank"
        />
        <BaseButton
          :title="__('Sync numbers', textDomain)"
          size="sm"
          :variant="state === 'error' ? 'secondary' : 'primary'"
          :loading="syncing"
          :disabled="syncing || Boolean(loading)"
          @click="emit('sync')"
        />
        <BaseButton
          :title="__('Manage numbers', textDomain)"
          size="sm"
          variant="secondary"
          @click="emit('manage-numbers')"
        />
        <BaseButton
          :title="__('Open panel', textDomain)"
          size="sm"
          variant="ghost"
          :href="panelUrl"
          target="_blank"
        />
      </div>
    </div>

    <!-- Connect with a key pasted by hand -->
    <div v-if="state === 'disconnected' && keyFormOpen" class="mt-6 max-w-2xl space-y-3 border-t border-slate-200 pt-6">
      <label class="block text-[13px] font-semibold text-slate-700" for="joinotify-connection-api-key">
        {{ __('API key', textDomain) }}
      </label>
      <div class="flex flex-wrap items-center gap-3">
        <input
          id="joinotify-connection-api-key"
          v-model="apiKey"
          type="password"
          autocomplete="off"
          spellcheck="false"
          placeholder="sk_live_..."
          :disabled="Boolean(loading)"
          class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary"
          @keyup.enter="connectWithKey"
        >
        <BaseButton
          :title="__('Connect', textDomain)"
          variant="secondary"
          :loading="loading === 'key'"
          :disabled="Boolean(loading) || !api"
          @click="connectWithKey"
        />
      </div>
      <p class="text-xs leading-5 text-slate-500">
        {{ sprintf(__('Connecting sends your key and this site’s address to %s so it can register the site and list your WhatsApp numbers.', textDomain), apiHost) }}
        <a class="font-semibold text-primary-700 underline underline-offset-2" :href="panelUrl" target="_blank" rel="noopener noreferrer">
          {{ __('Get a key on the Joinotify panel', textDomain) }}
        </a>
      </p>
    </div>

    <p v-if="errorMessage" class="mt-4 text-[13px] leading-5 text-rose-600" role="alert">{{ errorMessage }}</p>

    <!-- Advanced: the default origin and business account, filled in on connect -->
    <details v-if="state !== 'disconnected'" class="mt-6 border-t border-slate-200 pt-4">
      <summary class="cursor-pointer text-[13px] font-semibold text-slate-600">{{ __('Advanced', textDomain) }}</summary>

      <div class="mt-4 grid max-w-3xl gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-[13px] font-semibold text-slate-700" for="joinotify-connection-phone-id">{{ __('Phone number ID', textDomain) }}</label>
          <p class="mt-0.5 text-xs leading-5 text-slate-500">{{ __('Default origin for messages. Filled in when you connect; change it only to send from a different number by default.', textDomain) }}</p>
          <input
            id="joinotify-connection-phone-id"
            type="text"
            autocomplete="off"
            :readonly="!editingIds"
            :value="settings.whatsapp_phone_number_id || ''"
            class="mt-2 w-full rounded-lg border px-3 py-2 font-mono text-[13px] outline-none"
            :class="editingIds ? 'border-slate-300 bg-white text-slate-800 focus:border-primary' : 'cursor-default border-slate-200 bg-slate-50 text-slate-600'"
            @input="emit('update-setting', 'whatsapp_phone_number_id', $event.target.value)"
          >
        </div>
        <div>
          <label class="block text-[13px] font-semibold text-slate-700" for="joinotify-connection-waba-id">{{ __('WhatsApp Business Account ID', textDomain) }}</label>
          <p class="mt-0.5 text-xs leading-5 text-slate-500">{{ __('Business account that owns the message templates. Filled in when you connect.', textDomain) }}</p>
          <input
            id="joinotify-connection-waba-id"
            type="text"
            autocomplete="off"
            :readonly="!editingIds"
            :value="settings.whatsapp_waba_id || ''"
            class="mt-2 w-full rounded-lg border px-3 py-2 font-mono text-[13px] outline-none"
            :class="editingIds ? 'border-slate-300 bg-white text-slate-800 focus:border-primary' : 'cursor-default border-slate-200 bg-slate-50 text-slate-600'"
            @input="emit('update-setting', 'whatsapp_waba_id', $event.target.value)"
          >
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <BaseButton
          v-if="!editingIds"
          :title="__('Edit', textDomain)"
          size="sm"
          variant="secondary"
          @click="editingIds = true"
        />
        <p v-else class="text-xs text-slate-500">{{ __('Save the settings to keep the new IDs.', textDomain) }}</p>
      </div>

      <div v-if="!isLocked" class="mt-6 rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-[13px] font-semibold text-slate-700">{{ __('Disconnect this site', textDomain) }}</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
          {{ __('Removing the key stops all message delivery from this site. The numbers already imported are kept.', textDomain) }}
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <BaseButton
            v-if="!confirmingDisconnect"
            :title="__('Disconnect', textDomain)"
            size="sm"
            variant="secondary"
            :disabled="Boolean(loading)"
            @click="confirmingDisconnect = true"
          />
          <template v-else>
            <BaseButton
              :title="__('Confirm', textDomain)"
              size="sm"
              variant="danger"
              :loading="loading === 'disconnect'"
              :disabled="Boolean(loading)"
              @click="disconnect"
            />
            <BaseButton
              :title="__('Cancel', textDomain)"
              size="sm"
              variant="ghost"
              :disabled="Boolean(loading)"
              @click="confirmingDisconnect = false"
            />
          </template>
        </div>
      </div>
    </details>
  </section>
</template>
