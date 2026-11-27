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
 * Templates belong to one business account, and Meta looks one up on the
 * account of the sending number, so the list is the one of the action's
 * sender and each option is a name in one language. A saved template the
 * sender's account does not have is kept and pointed out.
 *
 * The template list, the preview and the variable fields are shared with the
 * test message of the settings (useWhatsappTemplates, TemplatePreview and
 * TemplateVariablesForm); the slot rules live in utils/whatsappTemplates.
 *
 * @since 2.3.0
 * @version 2.8.0
 */
import { computed, onMounted, watch } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import TemplatePreview from '../../../components/whatsapp/TemplatePreview.vue';
import TemplateVariablesForm from '../../../components/whatsapp/TemplateVariablesForm.vue';
import { useSenderOptions } from '../../../composables/useSenderOptions';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { useWhatsappTemplates } from '../../../composables/useWhatsappTemplates';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { createApiClient } from '../../../utils/api';
import { __, textDomain } from '../../../utils/i18n';
import { buildVariableMap, templateStatusWarning, type RemoteTemplate, type TemplateVariable } from '../../../utils/whatsappTemplates';
import type { WorkflowPlaceholderItem } from '../../../types/workflowBuilder';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array as () => Array<WorkflowPlaceholderItem | string>, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const store = useWorkflowBuilderStore();
const api = createApiClient(store.bootstrap);
const senderOptions = useSenderOptions(() => (props.modelValue as Record<string, unknown>).sender);
const { update } = useActionSettingsUpdate(props, emit);

// Meta looks a template up on the account of the number that sends it, so the
// list is the one of the action's sender. Without a sender (the action does
// not run without one) the API answers for the default account.
const senderWaba = computed(() => {
  const sender = String((props.modelValue as Record<string, unknown>).sender || '').trim();
  const senders = Array.isArray(store.bootstrap?.phones?.senders) ? store.bootstrap.phones.senders : [];
  const match = senders.find((item: unknown) => item && typeof item === 'object' && (item as Record<string, unknown>).phone === sender);

  return String((match as Record<string, unknown> | undefined)?.waba_id || '');
});

const { templates, loading, loaded, syncing, errorMsg, staleWarning, loadTemplates, syncTemplates } = useWhatsappTemplates(api, {
  wabaId: () => senderWaba.value,
});

const templateName = computed(() => String((props.modelValue as Record<string, unknown>).template_name || ''));
const templateLanguage = computed(() => String((props.modelValue as Record<string, unknown>).language || ''));

/**
 * Option value of a template: the same name can be approved in several languages.
 *
 * @since 2.8.0
 * @param {RemoteTemplate} template Template from the list.
 * @returns {string}
 */
function templateKeyOf(template: RemoteTemplate): string {
  return `${template.name}|${template.language || ''}`;
}

// The stored name and language pick the template; an action saved before the
// language was pinned falls back to the first one with that name.
const selected = computed(() =>
  templates.value.find((template) => template.name === templateName.value && template.language === templateLanguage.value)
  || templates.value.find((template) => template.name === templateName.value)
  || null
);

// A stored template the sender's account does not list would be refused with
// Meta's 132001, so it is pointed out instead of silently replaced.
const missingTemplate = computed(() =>
  Boolean(templateName.value) && loaded.value && !loading.value && !errorMsg.value && !selected.value
);

const storedVariables = computed<TemplateVariable[]>(() =>
  Array.isArray((props.modelValue as Record<string, unknown>).variables)
    ? ((props.modelValue as Record<string, unknown>).variables as TemplateVariable[])
    : []
);

const templateOptions = computed(() => {
  const options = templates.value.map((template) => ({
    value: templateKeyOf(template),
    label: template.language ? `${template.name} · ${template.language}` : template.name,
    meta: [template.category, template.status].filter(Boolean).join(' · '),
  }));

  // Keep a saved template visible in the field even when the list lacks it.
  if (missingTemplate.value) {
    options.unshift({
      value: `${templateName.value}|${templateLanguage.value}`,
      label: templateLanguage.value ? `${templateName.value} · ${templateLanguage.value}` : templateName.value,
      meta: '',
    });
  }

  return options;
});

const selectedKey = computed(() => {
  if (selected.value) {
    return templateKeyOf(selected.value);
  }

  return templateName.value ? `${templateName.value}|${templateLanguage.value}` : '';
});

// Anything other than APPROVED will be refused at send time, so it has to be
// visible while the workflow is being built, not discovered in the logs.
const blockingStatus = computed(() => (selected.value ? templateStatusWarning(selected.value.status) : ''));

/**
 * Store the chosen template with its language and variable slots in one change.
 *
 * The language is the one the template was approved in — sending another
 * language code is an error.
 *
 * @since 2.8.0
 * @param {string} key Option value, `name|language`.
 * @returns {void}
 */
function pickTemplate(key: string): void {
  const template = templates.value.find((item) => templateKeyOf(item) === key);

  if (!template) {
    return;
  }

  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    template_name: template.name,
    language: template.language || '',
    variables: buildVariableMap(template, storedVariables.value),
  });
}

// Once the list is known, the stored language and slots follow the template
// as Meta has it now. Nothing is emitted when they already match.
watch(selected, (template) => {
  if (!template) {
    return;
  }

  const language = template.language || templateLanguage.value;
  const variables = buildVariableMap(template, storedVariables.value);

  if (language === templateLanguage.value && JSON.stringify(variables) === JSON.stringify(storedVariables.value)) {
    return;
  }

  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    language,
    variables,
  });
});

// Another sender may belong to another account, with other templates.
watch(senderWaba, (wabaId, previous) => {
  if (wabaId !== previous) {
    loadTemplates();
  }
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
          :model-value="selectedKey"
          :options="templateOptions"
          :label="__('Template', textDomain)"
          :placeholder="loading ? __('Loading templates…', textDomain) : __('Select a template', textDomain)"
          :disabled="loading"
          @update:model-value="pickTemplate(String($event))"
        />

        <p v-if="missingTemplate" class="rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
          {{ __('The WhatsApp account of this sender does not have this template, so WhatsApp would refuse it. Choose a template from this list or another sender.', textDomain) }}
        </p>

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

      <TemplatePreview :template="selected" />

      <FieldGroup
        v-if="storedVariables.length"
        :title="__('Variables', textDomain)"
        :description="__('Each template variable is filled at send time with the placeholder you map here.', textDomain)"
      >
        <TemplateVariablesForm
          :variables="storedVariables"
          :placeholders="availablePlaceholders"
          @update:variables="update('variables', $event)"
        />
      </FieldGroup>
    </div>
  </div>
</template>
