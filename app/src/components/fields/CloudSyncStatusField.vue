<script setup>

/**
 * CloudSyncStatusField.vue frontend component.
 *
 * The Joinotify Cloud sync's monitor, at the top of its settings window: whether it runs and, when
 * it is paused, why and what to do; what waits in the outbox and what was given up on (to send
 * again or discard); and "Send existing customers", with an estimate to confirm and the report of
 * what the platform did with each row.
 *
 * Reads `admin/cloud-sync/status` and acts through `admin/cloud-sync/action`. Refreshes itself
 * while something is moving — a backfill running, rows waiting — and stops when nothing is.
 *
 * @since 2.5.0
 */
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import { __, sprintf, textDomain } from '../../utils/i18n';

const api = inject('joinotifyApi', null);

const status = ref(null);
const estimate = ref(null);
const loading = ref(false);
const busy = ref('');
const errorMsg = ref('');
const successMsg = ref('');
const confirming = ref(false);

let timer = null;

const outbox = computed(() => status.value?.outbox || {});
const dead = computed(() => (Array.isArray(status.value?.dead) ? status.value.dead : []));
const backfill = computed(() => status.value?.backfill || {});
const running = computed(() => backfill.value.status === 'running');
const moving = computed(() => running.value || Number(outbox.value.pending || 0) + Number(outbox.value.sending || 0) > 0);

const pauseText = computed(() => {
  const value = status.value || {};

  switch (value.paused) {
    case 'auth':
      return __('The Joinotify key of this site was revoked or cannot send this data. Connect the site again in the Connection tab; the sync resumes on its own.', textDomain);
    case 'site_missing':
      return __('This site was removed from your Joinotify account. Connect it again in the Connection tab to resume.', textDomain);
    case 'url_mismatch':
      return sprintf(
        /* translators: 1: this site's address, 2: the address the key was issued to */
        __('This site is at %1$s, but its Joinotify key was issued to %2$s. If this is a staging copy, leave it paused: it would send test orders to real customers. If the site moved, connect it again in the Connection tab.', textDomain),
        value.site_url || '',
        value.registered_url || __('another address', textDomain),
      );
    default:
      return '';
  }
});

const noteLabels = {
  no_phone: __('without a phone number', textDomain),
  invalid_phone: __('with an invalid phone number', textDomain),
  contact_limit_reached: __('over your plan\'s contact limit', textDomain),
  kept_opt_out: __('kept opted out', textDomain),
  kept_suppression: __('kept on the do-not-contact list', textDomain),
  conflict_retry: __('in conflict with another contact', textDomain),
  stale_snapshot: __('older than what Joinotify has', textDomain),
};

const notes = computed(() => Object.entries(backfill.value?.results?.notes || {}).map(([code, count]) => ({
  code,
  count,
  label: noteLabels[code] || code,
})));

/**
 * Format an ISO date in the viewer's locale.
 *
 * @since 2.5.0
 * @param {string|null} value ISO 8601.
 * @returns {string} Localized date and time, or an empty string.
 */
function formatDate(value) {
  if (!value) {
    return '';
  }

  const date = new Date(String(value).replace(' ', 'T'));

  return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
}

/**
 * Refresh while something moves, stop when nothing does.
 *
 * @since 2.5.0
 * @returns {void}
 */
function schedule() {
  if (timer) {
    clearTimeout(timer);
    timer = null;
  }

  if (moving.value) {
    timer = setTimeout(load, 10000);
  }
}

/**
 * Read the status.
 *
 * @since 2.5.0
 * @param {boolean} [withEstimate=false] Also count what a backfill would send.
 * @returns {Promise<void>}
 */
async function load(withEstimate = false) {
  if (!api) {
    return;
  }

  loading.value = true;

  try {
    const res = await api.get(`admin/cloud-sync/status${withEstimate === true ? '?estimate=1' : ''}`);

    status.value = res;

    if (res?.estimate) {
      estimate.value = res.estimate;
    }
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
    schedule();
  }
}

/**
 * Run an action, then read the status again.
 *
 * @since 2.5.0
 * @param {string} action Action name.
 * @returns {Promise<void>}
 */
async function act(action) {
  if (!api || busy.value) {
    return;
  }

  busy.value = action;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const res = await api.post('admin/cloud-sync/action', { action });

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Something went wrong.', textDomain);
    } else {
      successMsg.value = res?.message || '';
    }
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    busy.value = '';
    confirming.value = false;
    await load();
  }
}

/**
 * Show the estimate before sending anyone.
 *
 * @since 2.5.0
 * @returns {Promise<void>}
 */
async function askBackfill() {
  confirming.value = true;
  await load(true);
}

onMounted(() => load());
onBeforeUnmount(() => timer && clearTimeout(timer));
</script>

<template>
  <div class="space-y-3">
    <div v-if="!status && loading" class="rounded-[10px] border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] text-slate-500">
      {{ __('Loading the sync status…', textDomain) }}
    </div>

    <template v-else-if="status">
      <div
        class="rounded-[10px] border px-4 py-3"
        :class="status.paused ? 'border-amber-200 bg-amber-50' : status.enabled ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'"
      >
        <p class="text-[13px] font-semibold" :class="status.paused ? 'text-amber-800' : status.enabled ? 'text-emerald-800' : 'text-slate-700'">
          <template v-if="status.paused">{{ __('Sync paused', textDomain) }}</template>
          <template v-else-if="status.enabled">{{ __('Sync on', textDomain) }}</template>
          <template v-else>{{ __('Sync off', textDomain) }}</template>
        </p>

        <p class="mt-0.5 text-xs text-slate-600">
          <template v-if="status.paused">{{ pauseText }}</template>
          <template v-else-if="status.enabled">
            <template v-if="status.last_success_at">
              {{ sprintf(__('Last delivery: %s.', textDomain), formatDate(status.last_success_at)) }}
            </template>
            <template v-else>{{ __('Nothing delivered yet.', textDomain) }}</template>
            <template v-if="status.last_error"> {{ sprintf(__('Last error: %s.', textDomain), status.last_error) }}</template>
          </template>
          <template v-else>{{ __('Switch the sync on and save to start sending. Nothing is sent before that.', textDomain) }}</template>
        </p>

        <button
          v-if="status.paused"
          type="button"
          class="mt-2 inline-flex items-center rounded-[8px] border border-amber-300 bg-white px-3 py-1.5 text-[12px] font-medium text-amber-800 transition hover:bg-amber-100 disabled:opacity-50"
          :disabled="!!busy"
          @click="act('resume')"
        >{{ __('Try again', textDomain) }}</button>
      </div>

      <div v-if="status.enabled || outbox.sent || outbox.dead" class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded-[10px] border border-slate-200 bg-white px-3 py-2">
          <p class="text-lg font-semibold text-slate-800">{{ Number(outbox.pending || 0) + Number(outbox.sending || 0) }}</p>
          <p class="text-[11px] text-slate-500">{{ __('Waiting', textDomain) }}</p>
        </div>
        <div class="rounded-[10px] border border-slate-200 bg-white px-3 py-2">
          <p class="text-lg font-semibold text-slate-800">{{ outbox.sent || 0 }}</p>
          <p class="text-[11px] text-slate-500">{{ __('Sent (7 days)', textDomain) }}</p>
        </div>
        <div class="rounded-[10px] border px-3 py-2" :class="outbox.dead ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white'">
          <p class="text-lg font-semibold" :class="outbox.dead ? 'text-rose-700' : 'text-slate-800'">{{ outbox.dead || 0 }}</p>
          <p class="text-[11px] text-slate-500">{{ __('Given up', textDomain) }}</p>
        </div>
      </div>

      <div v-if="dead.length" class="rounded-[10px] border border-rose-200 bg-white px-4 py-3">
        <p class="text-[13px] font-semibold text-slate-700">{{ __('Given up after every retry', textDomain) }}</p>
        <ul class="mt-1 space-y-0.5 text-xs text-slate-600">
          <li v-for="(row, index) in dead" :key="index">
            <span class="font-mono">{{ row.name }}</span> — {{ row.error || __('no reason given', textDomain) }}
            <span class="text-slate-400">· {{ formatDate(row.created_at) }}</span>
          </li>
        </ul>
        <div class="mt-2 flex gap-2">
          <button
            type="button"
            class="inline-flex items-center rounded-[8px] bg-slate-900 px-3 py-1.5 text-[12px] font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
            :disabled="!!busy"
            @click="act('retry_dead')"
          >{{ __('Send again', textDomain) }}</button>
          <button
            type="button"
            class="inline-flex items-center rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-600 transition hover:border-rose-200 hover:text-rose-600 disabled:opacity-50"
            :disabled="!!busy"
            @click="act('discard_dead')"
          >{{ __('Discard', textDomain) }}</button>
        </div>
      </div>

      <div v-if="status.enabled" class="rounded-[10px] border border-slate-200 bg-white px-4 py-3">
        <p class="text-[13px] font-semibold text-slate-700">{{ __('Existing customers', textDomain) }}</p>

        <template v-if="running">
          <p class="mt-0.5 text-xs text-slate-600">
            {{ sprintf(__('Sending… %1$d queued so far, %2$d left out for having no phone number.', textDomain), backfill.queued || 0, backfill.no_phone || 0) }}
          </p>
          <button
            type="button"
            class="mt-2 inline-flex items-center rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-600 transition hover:border-rose-200 hover:text-rose-600 disabled:opacity-50"
            :disabled="!!busy"
            @click="act('backfill_cancel')"
          >{{ __('Stop', textDomain) }}</button>
        </template>

        <template v-else-if="confirming">
          <p class="mt-0.5 text-xs text-slate-600">
            <template v-if="estimate">
              {{ sprintf(__('%1$d of your %2$d users have a phone number, and there are %3$d orders placed without an account. Each person becomes a contact in Joinotify, tagged with this site; no flow starts for them. People without a phone number are left out, and contacts over your plan\'s limit are not created.', textDomain), estimate.users_with_phone || 0, estimate.users || 0, estimate.guest_orders || 0) }}
            </template>
            <template v-else>{{ __('Counting your customers…', textDomain) }}</template>
          </p>
          <div class="mt-2 flex gap-2">
            <button
              type="button"
              class="inline-flex items-center rounded-[8px] bg-slate-900 px-3 py-1.5 text-[12px] font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
              :disabled="!!busy || !estimate"
              @click="act('backfill_start')"
            >{{ __('Send them', textDomain) }}</button>
            <button
              type="button"
              class="inline-flex items-center rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-medium text-slate-600 transition hover:bg-slate-50"
              @click="confirming = false"
            >{{ __('Cancel', textDomain) }}</button>
          </div>
        </template>

        <template v-else>
          <p class="mt-0.5 text-xs text-slate-600">
            {{ __('Events reach only who buys or signs up from now on. Send the customers you already have, once, so your first campaign can reach them.', textDomain) }}
          </p>

          <div v-if="backfill.started_at && backfill.results" class="mt-2 space-y-0.5 text-xs text-slate-600">
            <p v-if="backfill.status === 'done'">{{ sprintf(__('Last run finished on %s.', textDomain), formatDate(backfill.finished_at)) }}</p>
            <p v-else-if="backfill.status === 'cancelled'">{{ __('Last run was stopped.', textDomain) }}</p>
            <p v-else-if="backfill.status === 'failed'">{{ __('Last run stopped because the sync was switched off.', textDomain) }}</p>
            <p>
              {{ sprintf(__('%1$d sent, %2$d waiting, %3$d given up, %4$d left out for having no phone number.', textDomain), backfill.results.sent || 0, backfill.results.pending || 0, backfill.results.dead || 0, backfill.no_phone || 0) }}
            </p>
            <p v-for="note in notes" :key="note.code">{{ sprintf(__('Of those sent, %1$d %2$s.', textDomain), note.count, note.label) }}</p>
          </div>

          <button
            type="button"
            class="mt-2 inline-flex items-center rounded-[8px] bg-slate-900 px-3 py-1.5 text-[12px] font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
            :disabled="!!busy"
            @click="askBackfill"
          >{{ __('Send existing customers', textDomain) }}</button>
        </template>
      </div>
    </template>

    <p v-if="successMsg" class="text-xs text-emerald-700">{{ successMsg }}</p>
    <p v-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>
  </div>
</template>
