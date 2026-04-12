<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { cloneValue } from '../../utils/object';
import { createAjaxClient } from '../../utils/api';
import BaseButton from '../../components/buttons/BaseButton.vue';
import SectionCard from '../../components/cards/SectionCard.vue';
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

const docsUrl = computed(() => props.bootstrap?.links?.docs_url || license.value?.docs_url || 'https://ajuda.meumouse.com/docs/joinotify/overview');
const purchaseUrl = computed(() => license.value?.purchase_url || props.bootstrap?.links?.purchase_url || 'https://meumouse.com/plugins/joinotify/');
const isActive = computed(() => Boolean(license.value?.is_valid));
const statusTone = computed(() => license.value?.status_tone || (isActive.value ? 'success' : 'danger'));
const cardTitle = computed(() => (isActive.value ? __('Licen�a ativa', textDomain) : inactiveTitle.value));
const cardDescription = computed(() =>
  isActive.value
    ? __('Confira os detalhes da sua licen�a e sincronize quando necess�rio.', textDomain)
    : inactiveSubtitle.value
);
const licenseField = computed(() => ({
  label: __('C�digo da licen�a', textDomain),
  description: __('Cole o c�digo recebido ap�s a compra da licen�a.', textDomain),
  placeholder: __('Ex.: CM-0000-0000-0000', textDomain),
}));
const inactiveTitle = computed(() => license.value?.title || __('Ative sua licen�a', textDomain));
const inactiveSubtitle = computed(() => license.value?.subtitle || __('Digite o c�digo da licen�a para liberar os recursos premium.', textDomain));
const activeRows = computed(() => [
  {
    label: __('Status da licença', textDomain),
    value: license.value?.status_label || __('Inválida', textDomain),
    badge: true,
  },
  {
    label: __('Assinatura', textDomain),
    value: normalizeValue(license.value?.subscription_label, ['Assinatura:', 'Tipo da licença:']),
  },
  {
    label: __('Licença expira em', textDomain),
    value: normalizeValue(license.value?.expire_label, ['Licença expira em:']),
  },
  {
    label: __('Sua chave de licença', textDomain),
    value: normalizeValue(license.value?.key_label, ['Sua chave de licença:']),
  },
]);

if (!form.license_key && license.value?.license_key) {
  form.license_key = license.value.license_key;
}



function normalizeValue(value, prefixes = []) {
  if (!value) {
    return __('Não disponível', textDomain);
  }

  let normalized = String(value);

  prefixes.forEach((prefix) => {
    normalized = normalized.replace(new RegExp(`^${escapeRegExp(prefix)}\\s*`), '');
  });

  return normalized.trim() || __('Não disponível', textDomain);
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
  license.value = { ...license.value, ...cloneValue(nextLicense || {}) };

  if (typeof nextLicense?.license_key === 'string') {
    form.license_key = nextLicense.license_key;
  }
}

function resetToInactiveState() {
  license.value = {
    ...initialLicense,
    is_valid: false,
    status_label: __('Inválida', textDomain),
    status_tone: 'danger',
    title: __('Ative sua licença', textDomain),
    subtitle: __('Digite o código da licença para liberar os recursos premium.', textDomain),
    license_title: __('Não disponível', textDomain),
    subscription_label: __('Ative sua licença para liberar os recursos premium.', textDomain),
    expire_label: __('Licença expira em: Não disponível', textDomain),
    support_label: __('Suporte até: Não disponível', textDomain),
    key_label: __('Sua chave de licença: Não disponível', textDomain),
    license_key_masked: __('Não disponível', textDomain),
    license_key: '',
  };
  form.license_key = '';
}

async function activateLicense() {
  const licenseKey = form.license_key.trim();

  if (!licenseKey) {
    pushToast(__('Digite o código da licença antes de ativar.', textDomain), 'warning', __('Licença', textDomain));
    return;
  }

  busyAction.value = 'activate';

  try {
    const response = await ajax.post(license.value?.activate_action || 'joinotify_active_license', {
      license_key: licenseKey,
    });

    pushToast(response?.toast_body_title || __('Licença ativada com sucesso.', textDomain), 'success', response?.toast_header_title || __('Licença', textDomain));
    await syncLicense(false);
  } catch (error) {
    pushToast(error.message || __('Não foi possível ativar a licença.', textDomain), 'error', __('Licença', textDomain));
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
      pushToast(response?.toast_body_title || __('As informações foram atualizadas com sucesso.', textDomain), 'info', response?.toast_header_title || __('Sincronização', textDomain));
    }
  } catch (error) {
    if (showToast) {
      pushToast(error.message || __('Não foi possível sincronizar a licença.', textDomain), 'error', __('Licença', textDomain));
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

    pushToast(response?.toast_body_title || __('A licença foi desativada.', textDomain), 'success', response?.toast_header_title || __('Licença', textDomain));
  } catch (error) {
    pushToast(error.message || __('Não foi possível desativar a licença.', textDomain), 'error', __('Licença', textDomain));
  } finally {
    busyAction.value = '';
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
  <div class="min-h-screen bg-[linear-gradient(180deg,#f4f7fb_0%,#eef2f7_100%)] px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-[1240px] flex-col gap-6">
      <header class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex items-start gap-4">
          <div class="mt-1 h-12 w-12 shrink-0 text-success">
            <svg viewBox="0 0 703 882.5" class="h-full w-full" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path
                d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z"
                transform="translate(-205.66 -112.03)"
                fill="currentColor"
              />
            </svg>
          </div>

          <div>
            <h1 class="text-[26px] font-normal leading-9 text-slate-800">
              {{ __('Joinotify: Automatize suas notificações. Simplifique sua comunicação.', textDomain) }}
            </h1>
            <p class="mt-5 max-w-[1180px] text-[16px] leading-6 text-slate-600">
              {{ __('Aumente a satisfação do seu cliente automatizando o envio de mensagens via WhatsApp com o Joinotify. Se precisar de ajuda para configurar, acesse nossa ', textDomain) }}
              <a class="font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">
                {{ __('Central de ajuda', textDomain) }}
              </a>
            </p>
          </div>
        </div>

        <div v-if="!isActive" class="lg:pt-1">
          <BaseButton
            :title="__('Comprar licença', textDomain)"
            color="white"
            size="lg"
            @click="openPurchaseUrl"
          />
        </div>
      </header>

      <SectionCard :title="cardTitle" :description="cardDescription" eyebrow="Licença">
        <div v-if="!isActive" class="space-y-6">
          <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(300px,0.85fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
              <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                  <h2 class="text-xl font-semibold text-ink">{{ __('Ativar licença', textDomain) }}</h2>
                  <p class="mt-2 text-sm leading-6 text-muted">
                    {{ __('Cole o código recebido após a compra e clique em ativar para liberar todos os recursos.', textDomain) }}
                  </p>
                </div>

                <StatusBadge :label="license.status_label || __('Inválida', textDomain)" :tone="statusTone" />
              </div>

              <div class="space-y-5">
                <TextField
                  v-model="form.license_key"
                  :field="licenseField"
                  name="joinotify_license_key"
                />

                <div class="flex flex-wrap items-center gap-3">
                  <BaseButton
                    :title="__('Ativar licença', textDomain)"
                    :loading="busyAction === 'activate'"
                    :disabled="busyAction === 'sync' || busyAction === 'deactivate'"
                    @click="activateLicense"
                  />
                  <BaseButton
                    :title="__('Sincronizar licença', textDomain)"
                    color="white"
                    :loading="busyAction === 'sync'"
                    :disabled="busyAction === 'activate' || busyAction === 'deactivate'"
                    @click="syncLicense"
                  />
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/70 p-6">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
                {{ __('O que você desbloqueia', textDomain) }}
              </p>
              <h3 class="mt-2 text-lg font-semibold text-ink">
                {{ __('Ative a licença para liberar os recursos premium do plugin.', textDomain) }}
              </h3>

              <ul class="mt-5 space-y-3 text-sm leading-6 text-slate-600">
                <li class="flex gap-3">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-700" />
                  <span>{{ __('Atualizações automáticas e sincronização da licença', textDomain) }}</span>
                </li>
                <li class="flex gap-3">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-700" />
                  <span>{{ __('Validação do acesso e liberação dos recursos premium', textDomain) }}</span>
                </li>
                <li class="flex gap-3">
                  <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-700" />
                  <span>{{ __('Acesso ao suporte e às atualizações da sua assinatura', textDomain) }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
            <h2 class="text-xl font-semibold text-ink">{{ __('Informações sobre a licença', textDomain) }}</h2>

            <div class="mt-6 space-y-4">
              <div v-for="row in activeRows" :key="row.label" class="flex flex-col gap-2 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between">
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
                :title="__('Desativar licença', textDomain)"
                color="primary"
                :loading="busyAction === 'deactivate'"
                :disabled="busyAction === 'sync' || busyAction === 'activate'"
                @click="deactivateLicense"
              />
              <BaseButton
                :title="__('Sincronizar licença', textDomain)"
                color="white"
                :loading="busyAction === 'sync'"
                :disabled="busyAction === 'deactivate' || busyAction === 'activate'"
                @click="syncLicense"
              />
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ __('Status da conta', textDomain) }}
            </p>
            <div class="mt-3 flex items-center gap-3">
              <StatusBadge :label="license.status_label || __('Válida', textDomain)" :tone="statusTone" />
              <span class="text-sm text-slate-500">{{ license.title || __('Licença ativa', textDomain) }}</span>
            </div>

            <p class="mt-4 text-sm leading-6 text-slate-600">
              {{ license.subtitle || __('Sua instalação está liberada para uso completo.', textDomain) }}
            </p>

            <div class="mt-6 rounded-xl bg-slate-50 p-4">
              <p class="text-sm font-semibold text-slate-700">{{ __('Ajuda rápida', textDomain) }}</p>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                {{ __('Se a licença não atualizar imediatamente, clique em sincronizar para buscar o estado atual no servidor.', textDomain) }}
              </p>
              <a class="mt-3 inline-flex text-sm font-semibold text-primary-700 underline underline-offset-4" :href="docsUrl" target="_blank" rel="noreferrer">
                {{ __('Abrir central de ajuda', textDomain) }}
              </a>
            </div>
          </div>
        </div>
      </SectionCard>
    </div>

    <ToastStack :toasts="toasts" @dismiss="dismissToast" />
  </div>
</template>
