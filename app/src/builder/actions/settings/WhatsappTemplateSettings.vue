<script setup lang="ts">
/**
 * WhatsappTemplateSettings.vue
 *
 * Settings panel for the "WhatsApp template message" action. Lists the
 * Meta-approved templates the account owns, previews the chosen one, and maps
 * each of its variables to a Joinotify placeholder.
 *
 * Templates belong to the WhatsApp Business Account and are the only way to
 * write to someone outside the 24-hour session window, so the panel also
 * surfaces the states that stop a template from being delivered (paused,
 * disabled, rejected).
 *
 * @since 2.3.0
 */
import { computed, onMounted, ref, watch } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useSenderOptions } from '../../../composables/useSenderOptions';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { createApiClient } from '../../../utils/api';
import { __, textDomain } from '../../../utils/i18n';

interface TemplateVariable {
  component: string;
  sub_type: string;
  index: number;
  key: string;
  label?: string;
  value?: string;
}

interface RemoteTemplate {
  name: string;
  language: string;
  status: string;
  category: string;
  header: string;
  body: string;
  footer: string;
  variables: TemplateVariable[];
}

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const store = useWorkflowBuilderStore();
const api = createApiClient(store.bootstrap);
const senderOptions = useSenderOptions(() => (props.modelValue as Record<string, unknown>).sender);
const { update } = useActionSettingsUpdate(props, emit);

const templates = ref<RemoteTemplate[]>([]);
const loading = ref(false);
const syncing = ref(false);
const errorMsg = ref('');
const staleWarning = ref('');

const templateName = computed(() => String((props.modelValue as Record<string, unknown>).template_name || ''));

const selected = computed(() => templates.value.find((template) => template.name === templateName.value) || null);

const templateOptions = computed(() =>
  templates.value.map((template) => ({
    value: template.name,
    label: template.name,
    meta: [template.category, template.language, template.status].filter(Boolean).join(' · '),
  }))
);

// Anything other than APPROVED will be refused at send time, so it has to be
// visible while the workflow is being built, not discovered in the logs.
const blockingStatus = computed(() => {
  if (!selected.value || selected.value.status === 'APPROVED') {
    return '';
  }

  switch (selected.value.status) {
    case 'PENDING':
      return __('This template is still awaiting Meta approval and cannot be delivered yet.', textDomain);
    case 'PAUSED':
      return __('Meta paused this template because of low quality. It will not be delivered until it recovers.', textDomain);
    case 'DISABLED':
      return __('Meta disabled this template. It can no longer be sent.', textDomain);
    case 'REJECTED':
      return __('Meta rejected this template.', textDomain);
    default:
      return '';
  }
});

/**
 * Build the stored variable map for a template, keeping any value already
 * mapped to the same placeholder.
 *
 * @since 2.3.0
 * @param {RemoteTemplate} template Template whose variables should be mapped.
 * @returns {TemplateVariable[]} The variable map to store on the action.
 */
function buildVariableMap(template: RemoteTemplate): TemplateVariable[] {
  const current = Array.isArray((props.modelValue as Record<string, unknown>).variables)
    ? ((props.modelValue as Record<string, unknown>).variables as TemplateVariable[])
    : [];

  return (template.variables || []).map((variable) => {
    const previous = current.find(
      (item) => item.component === variable.component && item.key === variable.key && Number(item.index) === Number(variable.index)
    );

    return {
      component: variable.component,
      sub_type: variable.sub_type || '',
      index: Number(variable.index || 0),
      key: variable.key,
      label: variable.label || '',
      value: previous?.value || '',
    };
  });
}

/**
 * Human-readable name of a variable slot.
 *
 * @since 2.3.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {string} Label shown above the placeholder field.
 */
function variableLabel(variable: TemplateVariable): string {
  if (variable.component === 'button') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · {{${variable.key}}}`;
  }

  if (variable.component === 'header') {
    return `${__('Header', textDomain)} · {{${variable.key}}}`;
  }

  return `${__('Body', textDomain)} · {{${variable.key}}}`;
}

/**
 * Store the value mapped to one variable slot.
 *
 * @since 2.3.0
 * @param {number} position Index of the slot in the stored map.
 * @param {string} value Placeholder or literal filling the slot.
 * @returns {void}
 */
function updateVariable(position: number, value: string): void {
  const variables = Array.isArray((props.modelValue as Record<string, unknown>).variables)
    ? [...((props.modelValue as Record<string, unknown>).variables as TemplateVariable[])]
    : [];

  if (!variables[position]) {
    return;
  }

  variables[position] = { ...variables[position], value };
  update('variables', variables);
}

/**
 * Load the templates of the account.
 *
 * @since 2.3.0
 * @param {boolean} [force] Ask the API to reconcile its mirror with Meta first.
 * @returns {Promise<void>}
 */
async function loadTemplates(force = false): Promise<void> {
  loading.value = true;
  errorMsg.value = '';
  staleWarning.value = '';

  try {
    const res = await api.get(`admin/builder/whatsapp-templates${force ? '?refresh=true' : ''}`);

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Could not load your message templates.', textDomain);
      return;
    }

    templates.value = Array.isArray(res?.templates) ? res.templates : [];

    if (res?.stale) {
      staleWarning.value = res.sync_error || __('This list may be out of date.', textDomain);
    }
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}

/**
 * Ask the API to reconcile its template mirror with Meta, then reload.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function syncTemplates(): Promise<void> {
  syncing.value = true;
  errorMsg.value = '';

  try {
    const res = await api.post('admin/builder/whatsapp-templates/sync', {});

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Could not sync your message templates.', textDomain);
      return;
    }

    templates.value = Array.isArray(res?.templates) ? res.templates : [];
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    syncing.value = false;
  }
}

// Picking a template rewrites the variable slots and pins the language the
// template was approved in — sending another language code is an error.
watch(selected, (template) => {
  if (!template) {
    return;
  }

  if (template.language && template.language !== (props.modelValue as Record<string, unknown>).language) {
    update('language', template.language);
  }

  update('variables', buildVariableMap(template));
});

onMounted(() => loadTemplates());
</script>

<template>
  <div class="space-y-4">
    <FieldGroup :title="__('Sender', textDomain)" :description="__('WhatsApp number that will send the template.', textDomain)">
      <BaseSelectField
        :model-value="String(modelValue.sender || '')"
        :options="senderOptions"
        :label="__('Sender', textDomain)"
        @update:model-value="update('sender', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Recipient', textDomain)" :description="__('Phone number or a placeholder that resolves to one.', textDomain)">
      <BaseTextFieldVariables
        :model-value="String(modelValue.receiver || '')"
        :label="__('Recipient', textDomain)"
        :placeholder="__('{{ wc_billing_phone }} or +5511999990000', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('receiver', $event)"
      />
    </FieldGroup>

    <FieldGroup
      :title="__('Template', textDomain)"
      :description="__('Approved templates of your WhatsApp Business Account. Only these can be delivered outside the 24-hour window.', textDomain)"
    >
      <div class="space-y-2">
        <BaseSelectField
          :model-value="templateName"
          :options="templateOptions"
          :label="__('Template', textDomain)"
          :placeholder="loading ? __('Loading templates…', textDomain) : __('Select a template', textDomain)"
          :disabled="loading"
          @update:model-value="update('template_name', $event)"
        />

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="text-xs font-medium text-slate-500 underline underline-offset-2 transition hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="syncing || loading"
            @click="syncTemplates"
          >
            {{ syncing ? __('Syncing…', textDomain) : __('Sync now', textDomain) }}
          </button>

          <span v-if="staleWarning" class="text-xs text-amber-600">{{ staleWarning }}</span>
        </div>

        <p v-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>

        <p v-else-if="!loading && !templates.length" class="text-xs text-slate-500">
          {{ __('No template found. Create one on the Joinotify panel and approve it with Meta first.', textDomain) }}
        </p>
      </div>
    </FieldGroup>

    <div v-if="selected" class="space-y-3">
      <p v-if="blockingStatus" class="rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
        {{ blockingStatus }}
      </p>

      <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3">
        <p v-if="selected.header" class="text-[13px] font-semibold text-slate-700">{{ selected.header }}</p>
        <p class="whitespace-pre-line text-[13px] leading-5 text-slate-600">{{ selected.body }}</p>
        <p v-if="selected.footer" class="mt-1 text-xs text-slate-400">{{ selected.footer }}</p>
      </div>

      <FieldGroup
        v-if="Array.isArray(modelValue.variables) && modelValue.variables.length"
        :title="__('Variables', textDomain)"
        :description="__('Each template variable is filled at send time with the placeholder you map here.', textDomain)"
      >
        <div class="space-y-3">
          <BaseTextFieldVariables
            v-for="(variable, position) in modelValue.variables"
            :key="`${variable.component}-${variable.index}-${variable.key}`"
            :model-value="String(variable.value || '')"
            :label="variableLabel(variable)"
            :placeholder="__('{{ wc_billing_first_name }} or a fixed value', textDomain)"
            :placeholders="availablePlaceholders"
            @update:model-value="updateVariable(position, $event)"
          />
        </div>
      </FieldGroup>
    </div>
  </div>
</template>
