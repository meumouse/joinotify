<script setup>

/**
 * LicensePage.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { cloneValue } from '../../utils/object';
import { createAjaxClient } from '../../utils/api';
import SettingsHeader from '../settings/components/SettingsHeader.vue';
import BaseButton from '../../components/buttons/BaseButton.vue';
import ConfirmDialog from '../../components/modals/ConfirmDialog.vue';
import StatusBadge from '../../components/cards/StatusBadge.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import TextField from '../../components/fields/TextField.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const ajax = createAjaxClient(props.bootstrap);
const initialLicense = cloneValue(props.bootstrap?.license || {});
const license = ref(initialLicense);
const form = reactive({
  license_key: initialLicense.license_key || '',
});
const toasts = ref([]);
const toastTimers = new Map();
const busyAction = ref('');
const confirm = reactive({ open: false, title: '', description: '', loading: false, action: null });

const docsUrl = computed(
  () => props.bootstrap?.links?.docs_url || license.value?.docs_url || 'https://ajuda.meumouse.com/docs/joinotify/overview'
);
const purchaseUrl = computed(
  () => license.value?.purchase_url || props.bootstrap?.links?.purchase_url || 'https://meumouse.com/plugins/joinotify/'
);
const isActive = computed(() => Boolean(license.value?.is_valid));
const statusTone = computed(() => license.value?.status_tone || (isActive.value ? 'success' : 'danger'));
const statusLabel = computed(() => (isActive.value ? __('Valid', textDomain) : __('Invalid', textDomain)));
const licenseField = computed(() => ({
  label: __('License key', textDomain),
  description: __('Paste the code you received after purchase.', textDomain),
  placeholder: __('Example: CM-0000-0000-0000', textDomain),
}));
const contentTitle = computed(() => (isActive.value ? __('License details', textDomain) : __('Activate license', textDomain)));
const contentDescription = computed(() =>
  isActive.value
    ? __('Review your current license status and refresh it whenever needed.', textDomain)
    : __('Enter your license key to unlock premium features.', textDomain)
);
const activeRows = computed(() => [
  {
    label: __('License status', textDomain),
    value: statusLabel.value,
    badge: true,
  },
  {
    label: __('Subscription', textDomain),
    value: normalizeValue(license.value?.subscription_label, ['Subscription:', 'License type:']),
  },
  {
    label: __('Expires on', textDomain),
    value: normalizeValue(license.value?.expire_label, ['Expires on:']),
  },
  {
    label: __('Support until', textDomain),
    value: normalizeValue(license.value?.support_label, ['Support until:']),
  },
  {
    label: __('Your license key', textDomain),
    value: normalizeValue(license.value?.key_label, ['Your license key:']),
  },
]);

if (!form.license_key && license.value?.license_key) {
  form.license_key = license.value.license_key;
}

function normalizeValue(value, prefixes = []) {
  if (!value) {
    return __('Not available', textDomain);
  }

  let normalized = String(value);

  prefixes.forEach((prefix) => {
    normalized = normalized.replace(new RegExp(`^${escapeRegExp(prefix)}\\s*`, 'i'), '');
  });

  return normalized.trim() || __('Not available', textDomain);
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function normalizeTone(tone) {
  if (tone === 'danger') return 'error';
  if (tone === 'success' || tone === 'warning' || tone === 'error' || tone === 'info') return tone;
  return 'info';
}

function openPurchaseUrl() {
  window.open(purchaseUrl.value, '_blank', 'noreferrer');
}

function pushToast(message, tone = 'info', title = __('Joinotify', textDomain)) {
  const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const normalizedTone = normalizeTone(tone);

  toasts.value.push({ id, title, message, tone: normalizedTone, closing: false });

  const hideTimer = window.setTimeout(() => {
    setToastClosing(id, true);
  }, 3000);

  const removeTimer = window.setTimeout(() => {
    removeToast(id);
  }, 3500);

  toastTimers.set(id, { hide: hideTimer, remove: removeTimer });
}

function clearToastTimers(id) {
  const timers = toastTimers.get(id);

  if (!timers) {
    return;
  }

  if (timers.hide) {
    window.clearTimeout(timers.hide);
  }

  if (timers.remove) {
    window.clearTimeout(timers.remove);
  }

  toastTimers.delete(id);
}

function removeToast(id) {
  clearToastTimers(id);
  toasts.value = toasts.value.filter((item) => item.id !== id);
}

function setToastClosing(id, closing) {
  toasts.value = toasts.value.map((item) => (item.id === id ? { ...item, closing } : item));
}

function dismissToast(id) {
  clearToastTimers(id);
  setToastClosing(id, true);

  const removeTimer = window.setTimeout(() => {
    toasts.value = toasts.value.filter((item) => item.id !== id);
  }, 180);

  toastTimers.set(id, { hide: null, remove: removeTimer });
}

function replaceLicenseData(nextLicense) {
  const normalized = cloneValue(nextLicense || {});

  license.value = normalized;

  if (typeof nextLicense?.license_key === 'string') {
    form.license_key = nextLicense.license_key;
  } else if (typeof normalized.license_key === 'string') {
    form.license_key = normalized.license_key;
  }
}

function resetToInactiveState() {
  license.value = {
    ...initialLicense,
    is_valid: false,
    status_label: __('Invalid', textDomain),
    status_tone: 'danger',
    title: __('Activate license', textDomain),
    subtitle: __('Enter your license key to unlock premium features.', textDomain),
    license_title: __('Not available', textDomain),
    subscription_label: __('Activate your license to unlock premium features.', textDomain),
    expire_label: __('Expires on: Not available', textDomain),
    support_label: __('Support until: Not available', textDomain),
    key_label: __('Your license key: Not available', textDomain),
    license_key_masked: __('Not available', textDomain),
    license_key: '',
  };
  form.license_key = '';
}

async function activateLicense() {
  const licenseKey = form.license_key.trim();

  if (!licenseKey) {
    pushToast(__('Enter a license key before activating.', textDomain), 'warning', __('License', textDomain));
    return;
  }

  busyAction.value = 'activate';

  try {
    const response = await ajax.post(license.value?.activate_action || 'joinotify_active_license', {
      license_key: licenseKey,
    });

    if (response?.license_data) {
      replaceLicenseData(response.license_data);
      pushToast(__('License activated successfully.', textDomain), 'success', __('License', textDomain));
    } else {
      await syncLicense(false);
      pushToast(__('License activated successfully.', textDomain), 'success', __('License', textDomain));
    }
  } catch (error) {
    pushToast(error.message || __('Could not activate the license.', textDomain), 'error', __('License', textDomain));
  } finally {
    busyAction.value = '';
  }
}

async function syncLicense(showToast = true) {
  busyAction.value = busyAction.value || 'sync';

  try {
    const response = await ajax.post(license.value?.sync_action || 'joinotify_sync_license', {});

    if (response?.license_data) {
      replaceLicenseData(response.license_data);
    }

    if (showToast) {
      pushToast(__('License information updated successfully.', textDomain), 'info', __('Sync', textDomain));
    }
  } catch (error) {
    if (showToast) {
      pushToast(error.message || __('Could not sync the license.', textDomain), 'error', __('License', textDomain));
    }
  } finally {
    busyAction.value = '';
  }
}

async function deactivateLicense() {
  busyAction.value = 'deactivate';

  try {
    const response = await ajax.post(license.value?.deactivate_action || 'joinotify_deactive_license', {});

    if (response?.license_data) {
      replaceLicenseData(response.license_data);
    } else {
      resetToInactiveState();
    }

    pushToast(__('License deactivated.', textDomain), 'success', __('License', textDomain));
  } catch (error) {
    pushToast(error.message || __('Could not deactivate the license.', textDomain), 'error', __('License', textDomain));
  } finally {
    busyAction.value = '';
  }
}

function confirmDeactivateLicense() {
  confirm.open = true;
  confirm.title = __('Deactivate license', textDomain);
  confirm.description = __('Are you sure you want to deactivate this license? This will disable premium features.', textDomain);
  confirm.action = async () => {
    confirm.loading = true;

    try {
      await deactivateLicense();
    } finally {
      confirm.loading = false;
      cancelConfirm();
    }
  };
}

function cancelConfirm() {
  confirm.open = false;
  confirm.title = '';
  confirm.description = '';
  confirm.action = null;
  confirm.loading = false;
}

async function runConfirm() {
  const action = confirm.action;

  if (typeof action === 'function') {
    await action();
  } else {
    cancelConfirm();
  }
}

onBeforeUnmount(() => {
  toastTimers.forEach((timer) => {
    window.clearTimeout(timer.hide);
    window.clearTimeout(timer.remove);
  });

  toastTimers.clear();
});
</script>

<template>
  <div class="joinotify-settings min-h-screen">
    <div class="w-full">
      <SettingsHeader :docs-url="docsUrl" />

      <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
        <div class="px-10 py-12">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-shell-500">
                {{ __('License', textDomain) }}
              </p>
              <h2 class="mt-1 text-xl font-semibold text-ink">{{ contentTitle }}</h2>
              <p class="mt-2 text-sm leading-6 text-muted">
                {{ contentDescription }}
              </p>
            </div>

            <div v-if="!isActive" class="lg:pt-1">
              <BaseButton
                :title="__('Buy license', textDomain)"
                color="white"
                size="lg"
                @click="openPurchaseUrl"
              />
            </div>
          </div>

          <div v-if="!isActive" class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(300px,0.85fr)]">
            <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
              <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                  <h3 class="text-lg font-semibold text-ink">{{ __('Activate license', textDomain) }}</h3>
                  <p class="mt-2 text-sm leading-6 text-muted">
                    {{ __('Paste the license key you received after purchase and click Activate to unlock all features.', textDomain) }}
                  </p>
                </div>

                <StatusBadge :label="statusLabel" :tone="statusTone" />
              </div>

              <div class="space-y-5">
                <TextField
                  v-model="form.license_key"
                  :field="licenseField"
                  name="joinotify_license_key"
                />

                <div class="flex flex-wrap items-center gap-3">
                  <BaseButton
                    :title="__('Activate license', textDomain)"
                    :loading="busyAction === 'activate'"
                    :disabled="busyAction === 'sync' || busyAction === 'deactivate'"
                    @click="activateLicense"
                  />
                  <BaseButton
                    :title="__('Buy license', textDomain)"
                    color="success"
                    @click="openPurchaseUrl"
                  />
                </div>
              </div>
            </div>

            <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 p-6">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
                {{ __('What you unlock', textDomain) }}
              </p>
              <h3 class="mt-2 text-lg font-semibold text-ink">
                {{ __('Activate the license to unlock Joinotify premium features.', textDomain) }}
              </h3>

              <ul class="mt-5 space-y-3 text-sm leading-6 text-slate-600">
                <li class="flex gap-3">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-700" />
                  <span>{{ __('Automatic updates and license synchronization', textDomain) }}</span>
                </li>
                <li class="flex gap-3">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-700" />
                  <span>{{ __('Access validation and premium feature unlocking', textDomain) }}</span>
                </li>
                <li class="flex gap-3">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-700" />
                  <span>{{ __('Support access and subscription updates', textDomain) }}</span>
                </li>
              </ul>
            </div>
          </div>

          <div v-else class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
              <h3 class="text-lg font-semibold text-ink">{{ __('License information', textDomain) }}</h3>

              <div class="mt-6 space-y-4">
                <div
                  v-for="row in activeRows"
                  :key="row.label"
                  class="flex flex-col gap-2 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
                >
                  <span class="text-[15px] font-medium text-slate-600">{{ row.label }}</span>
                  <span v-if="row.badge" class="sm:pl-4">
                    <StatusBadge :label="row.value" :tone="statusTone" />
                  </span>
                  <span v-else class="max-w-[70%] text-[15px] text-slate-800 sm:text-right">
                    {{ row.value }}
                  </span>
                </div>
              </div>

              <div class="mt-8 flex flex-wrap gap-3">
                <BaseButton
                  :title="__('Deactivate license', textDomain)"
                  color="primary"
                  :loading="busyAction === 'deactivate'"
                  :disabled="busyAction === 'sync' || busyAction === 'activate'"
                  @click="confirmDeactivateLicense"
                />
                <BaseButton
                  :title="__('Sync license', textDomain)"
                  color="white"
                  :loading="busyAction === 'sync'"
                  :disabled="busyAction === 'deactivate' || busyAction === 'activate'"
                  @click="syncLicense"
                />
              </div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
                {{ __('Account status', textDomain) }}
              </p>
              <div class="mt-3 flex items-center gap-3">
                <StatusBadge :label="statusLabel" :tone="statusTone" />
                <span class="text-sm text-slate-500">{{ __('Your installation is unlocked for full use.', textDomain) }}</span>
              </div>

              <p class="mt-4 text-sm leading-6 text-slate-600">
                {{ __('Your license is active. You can keep it synced here whenever the status changes on the server.', textDomain) }}
              </p>

              <div class="mt-6 rounded-[8px] bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-700">{{ __('Quick help', textDomain) }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                  {{ __('If the license does not update immediately, click Sync to fetch the latest status from the server.', textDomain) }}
                </p>
                <a
                  class="mt-3 inline-flex text-sm font-semibold text-primary-700 underline underline-offset-4"
                  :href="docsUrl"
                  target="_blank"
                  rel="noreferrer"
                >
                  {{ __('Open help center', textDomain) }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <ToastStack :toasts="toasts" @dismiss="dismissToast" />
    <ConfirmDialog
      :open="confirm.open"
      :title="confirm.title"
      :description="confirm.description"
      :loading="confirm.loading"
      @confirm="runConfirm"
      @cancel="cancelConfirm"
    />
  </div>
</template>
