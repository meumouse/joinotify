<script setup>

/**
 * OnboardingPage.vue frontend component.
 *
 * The setup wizard shown after activation and to installs that upgraded into a
 * version that has it. Each step writes its own values as the user moves on, so
 * closing the browser halfway through never loses what was already answered.
 *
 * @since 2.3.0
 */
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import { cloneValue } from '../../utils/object';
import { createApiClient } from '../../utils/api';
import PageHeader from '../../components/layout/PageHeader.vue';
import BaseButton from '../../components/base/BaseButton.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import StepIndicator from './components/StepIndicator.vue';
import CountryStep from './components/steps/CountryStep.vue';
import ConnectStep from './components/steps/ConnectStep.vue';
import AiStep from './components/steps/AiStep.vue';
import DocsStep from './components/steps/DocsStep.vue';
import PrivacyStep from './components/steps/PrivacyStep.vue';
import FinishStep from './components/steps/FinishStep.vue';

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const api = createApiClient(props.bootstrap);

const steps = computed(() => props.bootstrap?.steps || []);
const links = computed(() => props.bootstrap?.links || {});
const countryOptions = computed(() => props.bootstrap?.country?.options || []);
const countrySuggestion = computed(() => props.bootstrap?.country?.suggestion || {});
const aiProviders = computed(() => props.bootstrap?.ai?.providers || []);
const telemetry = computed(() => props.bootstrap?.telemetry || {});
const panelUrl = computed(() => props.bootstrap?.connection?.panel_url || '');
const apiHost = computed(() => props.bootstrap?.connection?.api_host || 'api.joinotify.com');

const savedSettings = cloneValue(props.bootstrap?.settings || {});
const connected = ref(Boolean(props.bootstrap?.connection?.connected));

// The AI key is write-only: the backend reports whether one is stored but never
// echoes it back, so the field starts blank and only sends what is typed.
const form = reactive({
  country: String(savedSettings.joinotify_default_country_code || countrySuggestion.value.code || ''),
  aiProvider: String(savedSettings.ai_provider || (aiProviders.value[0]?.id ?? '')),
  aiApiKey: '',
  usageTracking: savedSettings.enable_usage_tracking === 'yes' ? 'yes' : 'no',
});

const aiKeyStored = computed(() => {
  const provider = aiProviders.value.find((entry) => entry.id === form.aiProvider);

  return provider ? Boolean(savedSettings[`${provider.api_key_setting}_is_set`]) : false;
});

const currentIndex = ref(resolveInitialIndex());
const busy = ref(false);
const toasts = ref([]);
const toastTimers = new Map();

const currentStep = computed(() => steps.value[currentIndex.value] || {});
const isLastStep = computed(() => currentIndex.value >= steps.value.length - 1);
const canSkipStep = computed(() => Boolean(currentStep.value.optional));

/**
 * Resume where the user left off, without ever landing on the final step —
 * reaching "all set" is a decision, not a place to be restored to.
 *
 * @since 2.3.0
 * @returns {number} Index of the step to open.
 */
function resolveInitialIndex() {
  const savedStep = props.bootstrap?.state?.step || '';
  const list = props.bootstrap?.steps || [];
  const index = list.findIndex((step) => step.id === savedStep);

  if (index < 0) {
    return 0;
  }

  // The stored step is the last one saved, so resume on the one after it.
  return Math.min(index + 1, Math.max(list.length - 2, 0));
}

/**
 * Collect the settings a given step is responsible for.
 *
 * @since 2.3.0
 * @param {string} stepId Step identifier.
 * @returns {Object} Settings payload for that step.
 */
function valuesForStep(stepId) {
  if (stepId === 'country') {
    return { joinotify_default_country_code: form.country };
  }

  if (stepId === 'ai') {
    const provider = aiProviders.value.find((entry) => entry.id === form.aiProvider);
    const values = { ai_provider: form.aiProvider };
    const key = form.aiApiKey.trim();

    if (provider && key) {
      values[provider.api_key_setting] = key;

      if (provider.toggle_setting) {
        values[provider.toggle_setting] = 'yes';
      }
    }

    return values;
  }

  if (stepId === 'privacy') {
    return { enable_usage_tracking: form.usageTracking };
  }

  return {};
}

/**
 * Persist the current step and advance.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function goNext() {
  if (busy.value) {
    return;
  }

  const step = currentStep.value;
  const values = valuesForStep(step.id);

  busy.value = true;

  try {
    await api.post('/admin/onboarding/step', { step: step.id, values });

    if (step.id === 'ai' && form.aiApiKey.trim()) {
      // The key is stored now; stop holding it in the page.
      savedSettings[`${aiProviders.value.find((entry) => entry.id === form.aiProvider)?.api_key_setting}_is_set`] = true;
      form.aiApiKey = '';
    }

    currentIndex.value = Math.min(currentIndex.value + 1, steps.value.length - 1);
  } catch (error) {
    pushToast(error.message || __('Could not save this step.', textDomain), 'error');
  } finally {
    busy.value = false;
  }
}

/**
 * Move back one step without saving.
 *
 * @since 2.3.0
 * @returns {void}
 */
function goBack() {
  currentIndex.value = Math.max(currentIndex.value - 1, 0);
}

/**
 * Jump to an already-completed step.
 *
 * @since 2.3.0
 * @param {number} index Target step index.
 * @returns {void}
 */
function goToStep(index) {
  if (index < currentIndex.value) {
    currentIndex.value = index;
  }
}

/**
 * Leave the wizard without finishing it.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function skipWizard() {
  busy.value = true;

  try {
    await api.post('/admin/onboarding/skip', {});
  } catch (error) {
    // A failed dismissal only means the reminder shows again; still leave.
  } finally {
    busy.value = false;
    window.location.assign(links.value.workflows_url || links.value.settings_url || '');
  }
}

/**
 * Mark the wizard as finished and navigate to the chosen destination.
 *
 * @since 2.3.0
 * @param {string} destination Admin URL to open.
 * @returns {Promise<void>}
 */
async function finishWizard(destination) {
  if (busy.value) {
    return;
  }

  busy.value = true;

  try {
    await api.post('/admin/onboarding/complete', {});
    window.location.assign(destination || links.value.settings_url || '');
  } catch (error) {
    busy.value = false;
    pushToast(error.message || __('Could not finish the setup.', textDomain), 'error');
  }
}

/**
 * Record that the account was connected in the connect step.
 *
 * @since 2.3.0
 * @param {Object} result Response from the connect endpoint.
 * @returns {void}
 */
function onConnected(result) {
  connected.value = true;
  pushToast(result?.message || __('Your Joinotify account is connected.', textDomain), 'success');
}

function pushToast(message, tone = 'info') {
  const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;

  toasts.value.push({ id, title: __('Joinotify', textDomain), message, tone, closing: false });

  const removeTimer = window.setTimeout(() => dismissToast(id), 4000);

  toastTimers.set(id, removeTimer);
}

function dismissToast(id) {
  const timer = toastTimers.get(id);

  if (timer) {
    window.clearTimeout(timer);
    toastTimers.delete(id);
  }

  toasts.value = toasts.value.filter((item) => item.id !== id);
}

onBeforeUnmount(() => {
  toastTimers.forEach((timer) => window.clearTimeout(timer));
  toastTimers.clear();
});
</script>

<template>
  <div class="joinotify-settings mx-auto flex min-h-full w-full max-w-6xl flex-col px-4 py-8 sm:px-8 sm:py-12">
    <div class="flex w-full flex-1 flex-col">
      <div class="flex items-start justify-between gap-4">
        <PageHeader
          :title="__('Set up Joinotify', textDomain)"
          :description="__('A few questions and your site is ready to send WhatsApp messages. Everything here can be changed later in Settings.', textDomain)"
        />

        <!--
          The wizard covers the admin, so it has to carry its own way out. The
          footer link does the same thing, but it disappears on the last step
          and is easy to miss on a screen with nothing else around it.
        -->
        <button
          type="button"
          class="-mr-2 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-shell-500 outline-none transition hover:bg-white hover:text-ink focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-50"
          :title="__('Close setup', textDomain)"
          :aria-label="__('Close setup', textDomain)"
          :disabled="busy"
          @click="skipWizard"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
          </svg>
        </button>
      </div>

      <section class="mt-8 flex flex-1 flex-col overflow-hidden rounded-[8px] bg-white shadow-soft ring-1 ring-slate-100">
        <div class="grid flex-1 gap-0 lg:grid-cols-[minmax(220px,280px)_minmax(0,1fr)]">
          <aside class="border-b border-slate-100 bg-shell-50/40 px-6 py-8 lg:border-b-0 lg:border-r">
            <p class="mb-4 px-3 text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ __('Setup', textDomain) }}
            </p>

            <StepIndicator
              :steps="steps"
              :current-index="currentIndex"
              @navigate="goToStep"
            />
          </aside>

          <div class="flex min-h-[28rem] flex-col justify-between px-6 py-8 sm:px-10">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
                {{ __('Step', textDomain) }} {{ currentIndex + 1 }}/{{ steps.length }}
              </p>
              <h2 class="mt-1 text-xl font-semibold text-ink">{{ currentStep.title }}</h2>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">{{ currentStep.summary }}</p>

              <div class="mt-8">
                <CountryStep
                  v-if="currentStep.id === 'country'"
                  v-model="form.country"
                  :options="countryOptions"
                  :suggestion="countrySuggestion"
                />

                <ConnectStep
                  v-else-if="currentStep.id === 'connect'"
                  :api="api"
                  :connected="connected"
                  :panel-url="panelUrl"
                  :api-host="apiHost"
                  @connected="onConnected"
                />

                <AiStep
                  v-else-if="currentStep.id === 'ai'"
                  v-model:provider="form.aiProvider"
                  v-model:api-key="form.aiApiKey"
                  :providers="aiProviders"
                  :api-key-stored="aiKeyStored"
                />

                <DocsStep
                  v-else-if="currentStep.id === 'docs'"
                  :docs-url="links.docs_url"
                />

                <PrivacyStep
                  v-else-if="currentStep.id === 'privacy'"
                  v-model="form.usageTracking"
                  :telemetry="telemetry"
                />

                <FinishStep
                  v-else-if="currentStep.id === 'finish'"
                  :links="links"
                  :connected="connected"
                  :busy="busy"
                  @finish="finishWizard"
                />
              </div>
            </div>

            <div
              v-if="!isLastStep"
              class="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-6"
            >
              <button
                type="button"
                class="text-[13px] font-medium text-slate-500 underline underline-offset-4 transition hover:text-slate-700"
                :disabled="busy"
                @click="skipWizard"
              >
                {{ __('Skip setup for now', textDomain) }}
              </button>

              <div class="flex flex-wrap items-center gap-3">
                <BaseButton
                  v-if="currentIndex > 0"
                  :title="__('Back', textDomain)"
                  variant="secondary"
                  :disabled="busy"
                  @click="goBack"
                />

                <BaseButton
                  :title="canSkipStep && !form.aiApiKey ? __('Skip this step', textDomain) : __('Continue', textDomain)"
                  :loading="busy"
                  @click="goNext"
                />
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <ToastStack :toasts="toasts" @dismiss="dismissToast" />
  </div>
</template>
