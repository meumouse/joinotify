<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { __, textDomain } from './lib/i18n';
import { createApiClient } from './lib/api';
import SettingsHeader from './components/settings/SettingsHeader.vue';
import SectionTabs from './components/settings/SectionTabs.vue';
import GeneralSettingsSection from './components/settings/GeneralSettingsSection.vue';
import PhonesSettingsSection from './components/settings/PhonesSettingsSection.vue';
import IntegrationsSettingsSection from './components/settings/IntegrationsSettingsSection.vue';
import AboutSettingsSection from './components/settings/AboutSettingsSection.vue';
import SettingsActionBar from './components/settings/SettingsActionBar.vue';
import ProxySettingsModal from './components/settings/ProxySettingsModal.vue';
import IntegrationSettingsModal from './components/settings/IntegrationSettingsModal.vue';
import ConfirmDialog from './components/base/ConfirmDialog.vue';
import ToastStack from './components/base/ToastStack.vue';
import DebugLogModal from './components/cards/DebugLogModal.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const docsUrl = props.bootstrap?.docs_url || props.bootstrap?.docs || 'https://ajuda.meumouse.com/docs/joinotify/overview';
const api = createApiClient(props.bootstrap);
const bootstrap = ref(clone(props.bootstrap));
const settings = reactive({});
const savedSettings = ref(clone(props.bootstrap?.settings || {}));
const phoneCandidates = ref([]);
const debugLogs = ref([]);
const logsOpen = ref(false);
const saving = ref(false);
const refreshingSenderPhone = ref('');
const activeSectionId = ref('general');
const proxyConfigOpen = ref(false);
const integrationConfigOpen = ref(false);
const selectedIntegration = ref(null);
const toasts = ref([]);
const confirm = reactive({ open: false, title: '', description: '', action: null });
const toastTimers = new Map();

syncSettings(bootstrap.value.settings || {});

const sections = computed(() => bootstrap.value.schema || []);
const integrations = computed(() => bootstrap.value.integrations || []);
const phones = computed(() => bootstrap.value.phones || { senders: [], sender_count: 0 });
const system = computed(() => bootstrap.value.system || {});
const settingsFields = computed(() => flattenFields(bootstrap.value.schema || []));

const generalVisibleFields = computed(() => filterFields(['joinotify_default_country_code', 'enable_send_disconnect_notifications']));
const aboutVisibleFields = computed(() => filterFields(['enable_auto_updates', 'enable_update_notice']));
const proxyToggleField = computed(() => fieldFor('enable_proxy_api'));
const debugToggleField = computed(() => fieldFor('enable_debug_mode'));
const hasUnsavedChanges = computed(() => !deepEqual(settings, savedSettings.value));
const proxyDefaults = {
  send_text_proxy_api_route: 'send-message/text',
  send_media_proxy_api_route: 'send-message/media',
  proxy_api_key: '',
};

if (sections.value.length && !sections.value.some((section) => section.id === activeSectionId.value)) {
  activeSectionId.value = sections.value[0].id;
}

loadPhoneCandidates();

function clone(value) {
  return JSON.parse(JSON.stringify(value || {}));
}

function flattenFields(schema) {
  const fields = {};

  schema.forEach((section) => {
    (section.cards || []).forEach((card) => {
      (card.fields || []).forEach((field) => {
        fields[field.key] = field;
      });
    });
  });

  return fields;
}

function fieldFor(key) {
  return settingsFields.value[key] || { key, type: 'toggle', label: key, description: '' };
}

function filterFields(keys) {
  return keys.map((key) => fieldFor(key)).filter(Boolean);
}

function syncSettings(nextSettings) {
  Object.keys(settings).forEach((key) => delete settings[key]);
  Object.assign(settings, clone(nextSettings));
  savedSettings.value = clone(nextSettings);
}

function deepEqual(a, b) {
  if (a === b) {
    return true;
  }

  if (!a || !b || typeof a !== 'object' || typeof b !== 'object') {
    return false;
  }

  const aKeys = Object.keys(a).sort();
  const bKeys = Object.keys(b).sort();

  if (aKeys.length !== bKeys.length) {
    return false;
  }

  for (let index = 0; index < aKeys.length; index += 1) {
    if (aKeys[index] !== bKeys[index]) {
      return false;
    }
  }

  for (const key of aKeys) {
    const aValue = a[key];
    const bValue = b[key];

    if (Array.isArray(aValue) || Array.isArray(bValue)) {
      if (JSON.stringify(aValue) !== JSON.stringify(bValue)) {
        return false;
      }
      continue;
    }

    if (aValue && bValue && typeof aValue === 'object' && typeof bValue === 'object') {
      if (!deepEqual(aValue, bValue)) {
        return false;
      }
      continue;
    }

    if (aValue !== bValue) {
      return false;
    }
  }

  return true;
}

function normalizeToastTone(tone) {
  if (tone === 'danger') return 'error';
  if (tone === 'success' || tone === 'warning' || tone === 'error' || tone === 'info') return tone;
  return 'info';
}

function clearToastTimers(id) {
  const timers = toastTimers.get(id);

  if (!timers) return;

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

function toast(message, tone = 'info', title = __('Joinotify', textDomain)) {
  const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const normalizedTone = normalizeToastTone(tone);

  toasts.value.push({ id, title, message, tone: normalizedTone, closing: false });

  const hideTimer = window.setTimeout(() => {
    setToastClosing(id, true);
  }, 3000);

  const removeTimer = window.setTimeout(() => {
    removeToast(id);
  }, 3500);

  toastTimers.set(id, { hide: hideTimer, remove: removeTimer });
}

function dismissToast(id) {
  clearToastTimers(id);
  setToastClosing(id, true);

  const removeTimer = window.setTimeout(() => {
    toasts.value = toasts.value.filter((item) => item.id !== id);
  }, 180);

  toastTimers.set(id, { hide: null, remove: removeTimer });
}

function isEnabled(key) {
  return (settings[key] || 'no') === 'yes';
}

function toggleSetting(key) {
  if (!key) return;
  settings[key] = isEnabled(key) ? 'no' : 'yes';
}

function updateSetting(key, value) {
  settings[key] = value;
}

function resetProxyField(key) {
  if (!(key in proxyDefaults)) {
    return;
  }

  updateSetting(key, proxyDefaults[key]);
}

function generateProxyApiKey() {
  const generated = generateHexToken(32);
  updateSetting('proxy_api_key', generated);
}

function generateHexToken(length = 32) {
  const bytes = Math.ceil(length / 2);
  const buffer = new Uint8Array(bytes);

  if (window.crypto?.getRandomValues) {
    window.crypto.getRandomValues(buffer);
  } else {
    for (let index = 0; index < bytes; index += 1) {
      buffer[index] = Math.floor(Math.random() * 256);
    }
  }

  return Array.from(buffer, (value) => value.toString(16).padStart(2, '0')).join('').slice(0, length);
}

onBeforeUnmount(() => {
  toastTimers.forEach((timer) => window.clearTimeout(timer));
  toastTimers.clear();
});

async function saveSettings() {
  if (!hasUnsavedChanges.value) {
    return;
  }

  saving.value = true;

  try {
    const response = await api.post('/admin/settings', { settings });
    syncSettings(response.settings || {});
    bootstrap.value = { ...bootstrap.value, settings: clone(response.settings || {}) };
    toast(response.message || __('Settings have been saved.', textDomain), 'success', __('Saved', textDomain));
  } catch (error) {
    toast(error.message || __('Could not save.', textDomain), 'danger', __('Error', textDomain));
  } finally {
    saving.value = false;
  }
}

async function loadPhoneCandidates() {
  try {
    const response = await api.get('/admin/settings/phones/candidates');
    phoneCandidates.value = response.candidates || [];
  } catch (error) {
    phoneCandidates.value = [];
  }
}

async function registerPhone(phone) {
  try {
    const response = await api.post('/admin/settings/phones/register', { phone });
    toast(response.message || __('Code sent successfully.', textDomain), 'success', __('Phones', textDomain));
  } catch (error) {
    toast(error.message || __('Failed to send OTP.', textDomain), 'danger', __('Phones', textDomain));
  }
}

async function validateOtp(payload) {
  try {
    const response = await api.post('/admin/settings/phones/validate-otp', payload);
    syncPhones(response.phones || {});
    await loadPhoneCandidates();
    toast(response.message || __('Phone validated.', textDomain), 'success', __('Phones', textDomain));
  } catch (error) {
    toast(error.message || __('Validation failed.', textDomain), 'danger', __('Phones', textDomain));
  }
}

async function sendTestMessage(payload) {
  try {
    const response = await api.post('/admin/settings/phones/test-message', payload);
    toast(response.message || __('Message sent.', textDomain), 'success', __('Phones', textDomain));
  } catch (error) {
    toast(error.message || __('Failed to send message.', textDomain), 'danger', __('Phones', textDomain));
  }
}

function syncPhones(nextPhones) {
  bootstrap.value = { ...bootstrap.value, phones: clone(nextPhones) };
}

function confirmRemoveSender(phone) {
  confirm.open = true;
  confirm.title = __('Remove sender', textDomain);
  confirm.description = __('Are you sure you want to remove this sender?', textDomain);
  confirm.action = async () => {
    try {
      const response = await api.post('/admin/settings/phones/remove', { phone });
      syncPhones(response.phones || {});
      await loadPhoneCandidates();
      toast(response.message || __('Sender removed.', textDomain), 'success', __('Phones', textDomain));
    } catch (error) {
      toast(error.message || __('Could not remove.', textDomain), 'danger', __('Phones', textDomain));
    }
  };
}

async function refreshSenderConnection(phone) {
  refreshingSenderPhone.value = phone;

  try {
    const response = await api.post('/admin/settings/phones/check-connection', { phone });
    syncPhones({
      ...(phones.value || {}),
      senders: (phones.value.senders || []).map((item) =>
        item.phone === phone ? { ...item, connection: response.connection?.connection || item.connection } : item
      ),
    });
    toast(response.message || __('Connection updated.', textDomain), 'info', __('Phones', textDomain));
  } catch (error) {
    toast(error.message || __('Could not update the connection.', textDomain), 'danger', __('Phones', textDomain));
  } finally {
    refreshingSenderPhone.value = '';
  }
}

async function openLogs() {
  try {
    const response = await api.get('/admin/settings/debug/logs');
    debugLogs.value = response.content || [];
    logsOpen.value = true;
    if (!debugLogs.value.length) {
      toast(response.message || __('The debug log is empty.', textDomain), 'info', __('Logs', textDomain));
    }
  } catch (error) {
    toast(error.message || __('Could not open the logs.', textDomain), 'danger', __('Logs', textDomain));
  }
}

function confirmClearLogs() {
  confirm.open = true;
  confirm.title = __('Clear logs', textDomain);
  confirm.description = __('Are you sure you want to clear the debug logs?', textDomain);
  confirm.action = async () => {
    try {
      const response = await api.post('/admin/settings/debug/clear', {});
      debugLogs.value = [];
      toast(response.message || __('Logs cleared successfully.', textDomain), 'success', __('Logs', textDomain));
    } catch (error) {
      toast(error.message || __('Could not clear the logs.', textDomain), 'danger', __('Logs', textDomain));
    }
  };
}

function confirmReset() {
  confirm.open = true;
  confirm.title = __('Reset settings', textDomain);
  confirm.description = __('All options will return to the plugin defaults.', textDomain);
  confirm.action = async () => {
    try {
      const response = await api.post('/admin/settings/reset', {});
      bootstrap.value = clone(response.bootstrap || {});
      syncSettings(bootstrap.value.settings || {});
      await loadPhoneCandidates();
      toast(response.message || __('Options have been reset.', textDomain), 'success', __('Reset', textDomain));
    } catch (error) {
      toast(error.message || __('Could not reset.', textDomain), 'danger', __('Reset', textDomain));
    }
  };
}

function openIntegrationConfig(slug) {
  const integration = integrations.value.find((item) => item.slug === slug) || null;

  if (!canConfigureIntegration(integration)) {
    return;
  }

  selectedIntegration.value = integration;
  integrationConfigOpen.value = true;
}

function closeIntegrationConfig() {
  integrationConfigOpen.value = false;
  selectedIntegration.value = null;
}

async function runConfirm() {
  const action = confirm.action;
  cancelConfirm();

  if (typeof action === 'function') {
    await action();
  }
}

function cancelConfirm() {
  confirm.open = false;
  confirm.title = '';
  confirm.description = '';
  confirm.action = null;
}

function canConfigureIntegration(integration) {
  if (!integration) {
    return false;
  }

  return isEnabled(integration.setting_key) && Array.isArray(integration.fields) && integration.fields.length > 0;
}
</script>

<template>
  <div class="min-h-screen bg-[#f3f3f5]">
    <div class="w-full">
      <SettingsHeader :docs-url="docsUrl" />

      <SectionTabs
        :sections="sections"
        :active-section-id="activeSectionId"
        @select="activeSectionId = $event"
      />

      <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
        <div class="px-10 py-12">
          <GeneralSettingsSection
            v-if="activeSectionId === 'general'"
            :general-visible-fields="generalVisibleFields"
            :proxy-toggle-field="proxyToggleField"
            :settings="settings"
            @update-setting="updateSetting"
            @open-proxy-config="proxyConfigOpen = true"
          />

          <PhonesSettingsSection
            v-else-if="activeSectionId === 'phones'"
            :model-value="settings.test_number_phone"
            :phone-candidates="phoneCandidates"
            :phones="phones"
            :refreshing-sender-phone="refreshingSenderPhone"
            @update:model-value="updateSetting('test_number_phone', $event)"
            @register="registerPhone"
            @validate="validateOtp"
            @test-message="sendTestMessage"
            @remove="confirmRemoveSender"
            @refresh="refreshSenderConnection"
          />

          <IntegrationsSettingsSection
            v-else-if="activeSectionId === 'integrations'"
            :integrations="integrations"
            :settings="settings"
            @toggle="toggleSetting"
            @configure="openIntegrationConfig"
          />

          <AboutSettingsSection
            v-else-if="activeSectionId === 'about'"
            :about-visible-fields="aboutVisibleFields"
            :debug-toggle-field="debugToggleField"
            :settings="settings"
            :system="system"
            @update-setting="updateSetting"
            @open-logs="openLogs"
            @reset="confirmReset"
            @clear-logs="confirmClearLogs"
          />
        </div>

        <SettingsActionBar
          :saving="saving"
          :has-unsaved-changes="hasUnsavedChanges"
          @save="saveSettings"
        />
      </section>
    </div>

    <ToastStack :toasts="toasts" @dismiss="dismissToast" />

    <ProxySettingsModal
      :open="proxyConfigOpen"
      :settings="settings"
      @close="proxyConfigOpen = false"
      @update-setting="updateSetting"
      @reset-field="resetProxyField"
      @generate-key="generateProxyApiKey"
    />

    <IntegrationSettingsModal
      :open="integrationConfigOpen && canConfigureIntegration(selectedIntegration)"
      :integration="selectedIntegration"
      :settings="settings"
      @close="closeIntegrationConfig"
      @update-setting="updateSetting"
    />

    <DebugLogModal
      :open="logsOpen"
      :logs="debugLogs"
      @close="logsOpen = false"
      @clear="confirmClearLogs"
    />

    <ConfirmDialog
      :open="confirm.open"
      :title="confirm.title"
      :description="confirm.description"
      @confirm="runConfirm"
      @cancel="cancelConfirm"
    />
  </div>
</template>
