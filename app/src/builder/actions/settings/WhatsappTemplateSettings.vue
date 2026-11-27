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
import { buildVariableMap, templateStatusWarning, type TemplateVariable } from '../../../utils/whatsappTemplates';
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
const { templates, loading, syncing, errorMsg, staleWarning, loadTemplates, syncTemplates } = useWhatsappTemplates(api);

const templateName = computed(() => String((props.modelValue as Record<string, unknown>).template_name || ''));

const selected = computed(() => templates.value.find((template) => template.name === templateName.value) || null);

const storedVariables = computed<TemplateVariable[]>(() =>
  Array.isArray((props.modelValue as Record<string, unknown>).variables)
    ? ((props.modelValue as Record<string, unknown>).variables as TemplateVariable[])
    : []
);

const templateOptions = computed(() =>
  templates.value.map((template) => ({
    value: template.name,
    label: template.name,
    meta: [template.category, template.language, template.status].filter(Boolean).join(' · '),
  }))
);

// Anything other than APPROVED will be refused at send time, so it has to be
// visible while the workflow is being built, not discovered in the logs.
const blockingStatus = computed(() => (selected.value ? templateStatusWarning(selected.value.status) : ''));

// Picking a template rewrites the variable slots and pins the language the
// template was approved in — sending another language code is an error.
watch(selected, (template) => {
  if (!template) {
    return;
  }

  if (template.language && template.language !== (props.modelValue as Record<string, unknown>).language) {
    update('language', template.language);
  }

  update('variables', buildVariableMap(template, storedVariables.value));
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
