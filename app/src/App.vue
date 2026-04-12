<template>
  <div class="min-h-screen bg-[#f3f3f5]">
    <div class="w-full p-4">
      <header class="flex items-start gap-3">
        <div class="mt-1 h-12 w-12 shrink-0">
          <svg viewBox="0 0 703 882.5" class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z"
              transform="translate(-205.66 -112.03)"
              fill="#22c55e"
            />
          </svg>
        </div>

        <div class="pt-1">
          <h1 class="text-[26px] font-normal leading-9 text-slate-800">
            Joinotify: Automatize suas notificações. Simplifique sua comunicação.
          </h1>
          <p class="mt-6 max-w-[1180px] text-[16px] leading-6 text-slate-600">
            Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify.
            Se precisar de ajuda para configurar, acesse nossa
          <a class="font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">Central de ajuda</a>
          </p>
        </div>
      </header>

      <nav class="mt-10 flex w-fit overflow-hidden rounded-[8px] bg-[#e7edf5] p-0.5">
        <button
          v-for="section in sections"
          :key="section.id"
          type="button"
          class="flex min-w-[165px] items-center justify-center gap-2 px-6 py-5 text-[15px] font-semibold uppercase tracking-wide transition first:rounded-l-[8px] last:rounded-r-[8px] rounded-none"
          :class="activeSectionId === section.id ? 'bg-primary-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
          @click="activeSectionId = section.id"
        >
          <span v-html="sectionTabIcon(section.id)"></span>
          <span>{{ section.title }}</span>
        </button>
      </nav>

      <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
        <div class="px-10 py-12">
          <template v-if="activeSectionId === 'general'">
            <div class="space-y-2">
              <FieldRow
                v-for="field in generalVisibleFields"
                :key="field.key"
                :field="field"
                :name="field.key"
                :model-value="settings[field.key]"
                @update:model-value="updateSetting(field.key, $event)"
              />

              <div class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
                <div>
                  <h3 class="text-[15px] font-semibold text-slate-800">Ativar Proxy API</h3>
                  <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
                    Ative essa opção para ativar endpoints neste site para processar requisições de API do Joinotify.
                  </p>
                </div>

                <div class="flex items-center gap-4 lg:justify-self-start">
                  <FieldControl :field="proxyToggleField" name="enable_proxy_api" :model-value="settings.enable_proxy_api" @update:model-value="updateSetting('enable_proxy_api', $event)" />
                  <button
                    type="button"
            class="rounded-[8px] border border-primary-200 bg-white px-6 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50"
                    @click="proxyConfigOpen = true"
                  >
                    Configurar
                  </button>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="activeSectionId === 'phones'">
            <div class="space-y-10">
              <PhoneActions
                v-model="settings.test_number_phone"
                :candidates="phoneCandidates"
                :senders="phones.senders || []"
                @register="registerPhone"
                @validate="validateOtp"
                @test-message="sendTestMessage"
              />

              <PhoneSenderList
                :senders="phones.senders || []"
                @remove="confirmRemoveSender"
                @refresh="refreshSenderConnection"
              />
            </div>
          </template>

          <template v-else-if="activeSectionId === 'integrations'">
            <div class="grid gap-6 max-[1368px]:grid-cols-3 min-[1400px]:grid-cols-4">
              <IntegrationCard
                v-for="card in integrations"
                :key="card.slug"
                :card="card"
                :enabled="isEnabled(card.setting_key)"
                @toggle="toggleSetting(card.setting_key)"
                @configure="openIntegrationConfig"
              />
            </div>
          </template>

          <template v-else-if="activeSectionId === 'about'">
            <div class="space-y-8">
              <div class="space-y-2">
                <FieldRow
                  v-for="field in aboutVisibleFields"
                  :key="field.key"
                  :field="field"
                  :name="field.key"
                  :model-value="settings[field.key]"
                  @update:model-value="updateSetting(field.key, $event)"
                />
              </div>

              <div class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
                <div>
                  <h3 class="text-[15px] font-semibold text-slate-800">Ativar modo depuração</h3>
                  <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
                    Ative essa opção para ativar o modo depuração para verificar mensagens de erros e informações relevantes.
                  </p>
                </div>

                <div class="flex items-center gap-4 lg:justify-self-start">
                  <FieldControl :field="debugToggleField" name="enable_debug_mode" :model-value="settings.enable_debug_mode" @update:model-value="updateSetting('enable_debug_mode', $event)" />
                  <button
                    type="button"
            class="rounded-[8px] border border-primary-200 bg-white px-6 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50"
                    @click="openLogs"
                  >
                    Ver registros de depuração
                  </button>
                </div>
              </div>

              <div class="mt-6">
                <SystemStatusPanel :system="system" />
              </div>

              <div class="mt-8">
                <DangerZone @reset="confirmReset" @clear-logs="confirmClearLogs" />
              </div>
            </div>
          </template>
        </div>

        <div class="border-t border-slate-100 px-10 py-8">
          <button
            type="button"
          class="inline-flex items-center gap-2 rounded-[8px] bg-primary-700 px-6 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="saving"
            @click="saveSettings"
          >
            <span class="text-base leading-none">💾</span>
            <span>{{ saving ? 'Salvando...' : 'Salvar alterações' }}</span>
          </button>
        </div>
      </section>
    </div>

    <div class="pointer-events-none fixed right-3 top-12 z-[1090] w-[350px] max-w-full" aria-live="polite" aria-atomic="true">
      <TransitionGroup name="joinotify-toast" tag="div" class="space-y-3">
        <article
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto relative overflow-hidden rounded-lg border border-transparent bg-white shadow-[0_0.275rem_1.25rem_rgba(11,15,25,0.05),0_0.25rem_0.5625rem_rgba(11,15,25,0.03)] transition-all duration-200 ease-out"
          :class="[toastShellClass(toast.tone), toast.closing ? 'translate-y-1 opacity-0' : 'translate-y-0 opacity-100']"
        >
          <header
            class="flex items-center border-0 px-4 py-3 font-bold"
            :class="toastHeaderClass(toast.tone)"
          >
            <span class="me-2 inline-flex h-5 w-5 shrink-0" v-html="toastIcon(toast.tone)"></span>
            <span class="me-auto min-w-0 truncate">{{ toast.title }}</span>
            <button
              type="button"
              class="ms-2 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded border-0 bg-transparent text-current opacity-60 transition hover:opacity-100"
              aria-label="Fechar toast"
              @click="dismissToast(toast.id)"
            >
              <span class="sr-only">Fechar</span>
              <span aria-hidden="true">×</span>
            </button>
          </header>
          <div class="px-4 py-4 text-[15px] leading-6 text-slate-600">
            {{ toast.message }}
          </div>
          <div class="h-[3px] w-full origin-left" :class="toastProgressClass(toast.tone)" />
        </article>
      </TransitionGroup>
    </div>

    <ModalDialog
      :open="proxyConfigOpen"
      title="Configurar Proxy API"
      description="Ajuste as rotas e a chave da API usada pelo proxy."
      eyebrow="Geral"
      @close="proxyConfigOpen = false"
    >
      <div class="space-y-4">
        <FieldRow
          v-for="field in proxyFields"
          :key="field.key"
          :field="field"
          :name="field.key"
          :model-value="settings[field.key]"
          @update:model-value="updateSetting(field.key, $event)"
        />
      </div>
    </ModalDialog>

    <ModalDialog
      :open="integrationConfigOpen"
      :title="selectedIntegration?.title || 'Configurações da integração'"
      :description="selectedIntegration?.description || ''"
      eyebrow="Integrações"
      @close="closeIntegrationConfig"
    >
      <div v-if="selectedIntegration?.fields?.length" class="space-y-4">
        <FieldRow
          v-for="field in selectedIntegration.fields"
          :key="field.key"
          :field="field"
          :name="field.key"
          :model-value="settings[field.key]"
          @update:model-value="updateSetting(field.key, $event)"
        />
      </div>
      <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
        Essa integração não possui configurações adicionais.
      </div>
    </ModalDialog>

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

<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { createApiClient } from './lib/api';
import FieldRow from './components/fields/FieldRow.vue';
import FieldControl from './components/fields/FieldControl.vue';
import ModalDialog from './components/base/ModalDialog.vue';
import ConfirmDialog from './components/base/ConfirmDialog.vue';
import IntegrationCard from './components/cards/IntegrationCard.vue';
import PhoneSenderList from './components/cards/PhoneSenderList.vue';
import PhoneActions from './components/cards/PhoneActions.vue';
import SystemStatusPanel from './components/cards/SystemStatusPanel.vue';
import DangerZone from './components/cards/DangerZone.vue';
import DebugLogModal from './components/cards/DebugLogModal.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const docsUrl = props.bootstrap?.docs_url || props.bootstrap?.docs || 'https://ajuda.meumouse.com/docs/joinotify/overview';
const api = createApiClient(props.bootstrap);
const bootstrap = ref(clone(props.bootstrap));
const settings = reactive({});
const phoneCandidates = ref([]);
const debugLogs = ref([]);
const logsOpen = ref(false);
const saving = ref(false);
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
const proxyFields = computed(() => filterFields(['send_text_proxy_api_route', 'send_media_proxy_api_route', 'proxy_api_key']));
const proxyToggleField = computed(() => fieldFor('enable_proxy_api'));
const debugToggleField = computed(() => fieldFor('enable_debug_mode'));

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
}

function sectionTabIcon(id) {
  const icons = {
    general: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 8h16M6 12h12M8 16h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    phones: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10v18H7z" stroke="currentColor" stroke-width="2" /><path d="M9 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    integrations: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v8m0 2v8M7 7l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    about: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 10v6M12 7h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  };

  return icons[id] || icons.general;
}

function toastIcon(tone) {
  const icons = {
    success:
      '<svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg>',
    warning:
      '<svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1.75 20h20.5L12 2zm0 4.65 6.06 10.65H5.94L12 6.65z"></path><path d="M11 9h2v5h-2zm0 6h2v2h-2z"></path></svg>',
    error:
      '<svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 11h2v6h-2zm0-4h2v2h-2z"></path></svg>',
    info:
      '<svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path d="M11 10h2v7h-2zm0-4h2v2h-2z"></path></svg>',
  };

  return icons[tone] || icons.info;
}

function normalizeToastTone(tone) {
  if (tone === 'danger') return 'error';
  if (tone === 'success' || tone === 'warning' || tone === 'error' || tone === 'info') return tone;
  return 'info';
}

function toastShellClass(tone) {
  const classes = {
    success: 'toast-success',
    warning: 'toast-warning',
    error: 'toast-danger',
    info: 'toast-info',
  };

  return classes[tone] || classes.info;
}

function toastHeaderClass(tone) {
  const classes = {
    success: 'bg-success text-white',
    warning: 'bg-warning text-dark',
    error: 'bg-danger text-white',
    info: 'bg-info text-white',
  };

  return classes[tone] || classes.info;
}

function toastProgressClass(tone) {
  const classes = {
    success: 'bg-success [animation:joinotify-toast-progress_3s_linear_forwards]',
    warning: 'bg-warning [animation:joinotify-toast-progress_3s_linear_forwards]',
    error: 'bg-danger [animation:joinotify-toast-progress_3s_linear_forwards]',
    info: 'bg-info [animation:joinotify-toast-progress_3s_linear_forwards]',
  };

  return classes[tone] || classes.info;
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

function toast(message, tone = 'info', title = 'Joinotify') {
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

onBeforeUnmount(() => {
  toastTimers.forEach((timer) => window.clearTimeout(timer));
  toastTimers.clear();
});

async function saveSettings() {
  saving.value = true;

  try {
    const response = await api.post('/admin/settings', { settings });
    syncSettings(response.settings || {});
    bootstrap.value = { ...bootstrap.value, settings: clone(response.settings || {}) };
    toast(response.message || 'As configurações foram salvas.', 'success', 'Salvo');
  } catch (error) {
    toast(error.message || 'Não foi possível salvar.', 'danger', 'Erro');
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
    toast(response.message || 'Código enviado com sucesso.', 'success', 'Telefones');
  } catch (error) {
    toast(error.message || 'Falha ao enviar OTP.', 'danger', 'Telefones');
  }
}

async function validateOtp(payload) {
  try {
    const response = await api.post('/admin/settings/phones/validate-otp', payload);
    syncPhones(response.phones || {});
    await loadPhoneCandidates();
    toast(response.message || 'Telefone validado.', 'success', 'Telefones');
  } catch (error) {
    toast(error.message || 'Falha na validação.', 'danger', 'Telefones');
  }
}

async function sendTestMessage(payload) {
  try {
    const response = await api.post('/admin/settings/phones/test-message', payload);
    toast(response.message || 'Mensagem enviada.', 'success', 'Telefones');
  } catch (error) {
    toast(error.message || 'Falha ao enviar mensagem.', 'danger', 'Telefones');
  }
}

function syncPhones(nextPhones) {
  bootstrap.value = { ...bootstrap.value, phones: clone(nextPhones) };
}

function confirmRemoveSender(phone) {
  confirm.open = true;
  confirm.title = 'Remover remetente';
  confirm.description = 'Tem certeza que deseja remover este remetente?';
  confirm.action = async () => {
    try {
      const response = await api.post('/admin/settings/phones/remove', { phone });
      syncPhones(response.phones || {});
      await loadPhoneCandidates();
      toast(response.message || 'Remetente removido.', 'success', 'Telefones');
    } catch (error) {
      toast(error.message || 'Não foi possível remover.', 'danger', 'Telefones');
    }
  };
}

async function refreshSenderConnection(phone) {
  try {
    const response = await api.post('/admin/settings/phones/check-connection', { phone });
    syncPhones({
      ...(phones.value || {}),
      senders: (phones.value.senders || []).map((item) =>
        item.phone === phone ? { ...item, connection: response.connection?.connection || item.connection } : item
      ),
    });
    toast(response.message || 'Conexão atualizada.', 'info', 'Telefones');
  } catch (error) {
    toast(error.message || 'Não foi possível atualizar a conexão.', 'danger', 'Telefones');
  }
}

async function openLogs() {
  try {
    const response = await api.get('/admin/settings/debug/logs');
    debugLogs.value = response.content || [];
    logsOpen.value = true;
    if (!debugLogs.value.length) {
      toast(response.message || 'O registro de depuração está vazio.', 'info', 'Logs');
    }
  } catch (error) {
    toast(error.message || 'Não foi possível abrir os logs.', 'danger', 'Logs');
  }
}

function confirmClearLogs() {
  confirm.open = true;
  confirm.title = 'Limpar registros';
  confirm.description = 'Tem certeza que deseja limpar os registros de depuração?';
  confirm.action = async () => {
    try {
      const response = await api.post('/admin/settings/debug/clear', {});
      debugLogs.value = [];
      toast(response.message || 'Logs limpos com sucesso.', 'success', 'Logs');
    } catch (error) {
      toast(error.message || 'Não foi possível limpar os logs.', 'danger', 'Logs');
    }
  };
}

function confirmReset() {
  confirm.open = true;
  confirm.title = 'Redefinir configurações';
  confirm.description = 'Todas as opções voltarão ao padrão definido pelo plugin.';
  confirm.action = async () => {
    try {
      const response = await api.post('/admin/settings/reset', {});
      bootstrap.value = clone(response.bootstrap || {});
      syncSettings(bootstrap.value.settings || {});
      await loadPhoneCandidates();
      toast(response.message || 'As opções foram redefinidas.', 'success', 'Reset');
    } catch (error) {
      toast(error.message || 'Não foi possível redefinir.', 'danger', 'Reset');
    }
  };
}

function openIntegrationConfig(slug) {
  selectedIntegration.value = integrations.value.find((item) => item.slug === slug) || null;
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
</script>
