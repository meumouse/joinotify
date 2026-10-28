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
 * Besides the `{{...}}` tokens, a template may need values it has no token
 * for: the file or location of the header, the coupon of a copy-code button,
 * the login code of an authentication button and the expiration of a
 * limited-time offer. Those slots carry a `format` (or a button sub-type) and
 * get their own label and hint. A carousel lists the slots of each card, which
 * carry the card position in `card`.
 *
 * @since 2.3.0
 * @version 2.6.0
 */
import { computed, onMounted, ref, watch } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useSenderOptions } from '../../../composables/useSenderOptions';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { createApiClient } from '../../../utils/api';
import { ImagePlus } from '@boxicons/vue';
import { __, textDomain } from '../../../utils/i18n';
import type { WorkflowPlaceholderItem } from '../../../types/workflowBuilder';

interface TemplateVariable {
  card?: number;
  component: string;
  sub_type: string;
  index: number;
  key: string;
  label?: string;
  format?: string;
  value?: string;
}

interface TemplateButton {
  type: string;
  text: string;
  url: string;
}

interface TemplateCard {
  header_format: string;
  body: string;
  buttons: TemplateButton[];
}

interface RemoteTemplate {
  name: string;
  language: string;
  status: string;
  category: string;
  header_format?: string;
  header: string;
  body: string;
  footer: string;
  buttons?: TemplateButton[];
  cards?: TemplateCard[];
  variables: TemplateVariable[];
}

const MEDIA_FORMATS = ['image', 'video', 'document'];

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
 * @version 2.6.0
 * @param {RemoteTemplate} template Template whose variables should be mapped.
 * @returns {TemplateVariable[]} The variable map to store on the action.
 */
function buildVariableMap(template: RemoteTemplate): TemplateVariable[] {
  const current = Array.isArray((props.modelValue as Record<string, unknown>).variables)
    ? ((props.modelValue as Record<string, unknown>).variables as TemplateVariable[])
    : [];

  return (template.variables || []).map((variable) => {
    // Token-less slots share an empty key, so the sub-type and format tell a
    // coupon from a login code on the same button position.
    const previous = current.find(
      (item) =>
        item.component === variable.component &&
        item.key === variable.key &&
        Number(item.index) === Number(variable.index) &&
        cardOf(item) === cardOf(variable) &&
        (item.sub_type || '') === (variable.sub_type || '') &&
        (item.format || '') === (variable.format || '')
    );

    return {
      card: cardOf(variable),
      component: variable.component,
      sub_type: variable.sub_type || '',
      index: Number(variable.index || 0),
      key: variable.key,
      label: variable.label || '',
      format: variable.format || '',
      value: previous?.value || '',
    };
  });
}

/**
 * Carousel card a slot belongs to, -1 outside a carousel.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {number}
 */
function cardOf(variable: TemplateVariable): number {
  return variable.card === undefined || variable.card === null ? -1 : Number(variable.card);
}

/**
 * Whether a slot holds the file of a media header.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {boolean}
 */
function isMediaHeader(variable: TemplateVariable): boolean {
  return variable.component === 'header' && MEDIA_FORMATS.includes(variable.format || '');
}

/**
 * Human-readable name of a variable slot, prefixed with its carousel card.
 *
 * @since 2.3.0
 * @version 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {string} Label shown above the placeholder field.
 */
function variableLabel(variable: TemplateVariable): string {
  const card = cardOf(variable);

  return card >= 0 ? `${__('Card', textDomain)} ${card + 1} · ${slotLabel(variable)}` : slotLabel(variable);
}

/**
 * Name of a variable slot within its message or card.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {string}
 */
function slotLabel(variable: TemplateVariable): string {
  if (variable.component === 'header' && variable.format === 'location') {
    switch (variable.key) {
      case 'latitude':
        return `${__('Header location', textDomain)} · ${__('Latitude', textDomain)}`;
      case 'longitude':
        return `${__('Header location', textDomain)} · ${__('Longitude', textDomain)}`;
      case 'name':
        return `${__('Header location', textDomain)} · ${__('Name', textDomain)}`;
      default:
        return `${__('Header location', textDomain)} · ${__('Address', textDomain)}`;
    }
  }

  if (variable.component === 'limited_time_offer') {
    return __('Offer expiration', textDomain);
  }

  if (variable.component === 'button' && variable.sub_type === 'quick_reply') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · ${__('Reply payload', textDomain)}`;
  }

  if (isMediaHeader(variable)) {
    switch (variable.format) {
      case 'video':
        return __('Header video', textDomain);
      case 'document':
        return __('Header document', textDomain);
      default:
        return __('Header image', textDomain);
    }
  }

  if (variable.component === 'button' && variable.sub_type === 'copy_code') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · ${__('Coupon code', textDomain)}`;
  }

  if (variable.component === 'button' && variable.format === 'otp') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · ${__('Verification code', textDomain)}`;
  }

  if (variable.component === 'button') {
    return `${__('Button', textDomain)} ${Number(variable.index) + 1} · {{${variable.key}}}`;
  }

  if (variable.component === 'header') {
    return `${__('Header', textDomain)} · {{${variable.key}}}`;
  }

  return `${__('Body', textDomain)} · {{${variable.key}}}`;
}

/**
 * Example shown inside the field of a variable slot.
 *
 * @since 2.6.0
 * @param {TemplateVariable} variable Variable slot.
 * @returns {string}
 */
function variablePlaceholder(variable: TemplateVariable): string {
  if (isMediaHeader(variable)) {
    return __('https://example.com/file.jpg or a placeholder', textDomain);
  }

  if (variable.sub_type === 'copy_code') {
    return __('BLACK20 or a placeholder', textDomain);
  }

  if (variable.format === 'otp') {
    return __('Leave empty to repeat the code from the body', textDomain);
  }

  if (variable.format === 'location') {
    return ['latitude', 'longitude'].includes(variable.key)
      ? __('-23.5505 or a placeholder', textDomain)
      : __('Optional', textDomain);
  }

  if (variable.component === 'limited_time_offer') {
    return __('2026-12-31 23:59, +2 days or a placeholder', textDomain);
  }

  if (variable.sub_type === 'quick_reply') {
    return __('Optional: sent back when the button is tapped', textDomain);
  }

  return __('{{ wc_billing_first_name }} or a fixed value', textDomain);
}

/**
 * Open the WordPress media library and store the chosen file's URL on a
 * media header slot.
 *
 * @since 2.6.0
 * @param {number} position Index of the slot in the stored map.
 * @param {string} format Media kind the template header accepts.
 * @returns {void}
 */
function openMediaLibrary(position: number, format: string): void {
  const wpMedia = (window as Window & {
    wp?: {
      media?: (...args: unknown[]) => {
        on: (event: string, callback: () => void) => void;
        state: () => { get: (key: string) => { first: () => { toJSON: () => { url?: string } } | undefined } };
        open: () => void;
      };
    };
  }).wp?.media;

  if (!wpMedia) {
    return;
  }

  // The library has no "document" type; any file stays selectable for it.
  const library = format === 'image' || format === 'video' ? { type: format } : {};
  const frame = wpMedia({ title: __('Select media', textDomain), button: { text: __('Use this media', textDomain) }, library, multiple: false });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first();
    const url = String(attachment?.toJSON?.().url || '').trim();

    if (url) {
      updateVariable(position, url);
    }
  });

  frame.open();
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
        <p
          v-if="selected.header_format && selected.header_format !== 'TEXT'"
          class="mb-2 inline-flex items-center gap-1 rounded-[6px] bg-slate-200 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-600"
        >
          <ImagePlus class="text-sm" />
          {{ String(selected.header_format).toLowerCase() }}
        </p>
        <p v-if="selected.header" class="text-[13px] font-semibold text-slate-700">{{ selected.header }}</p>
        <p class="whitespace-pre-line text-[13px] leading-5 text-slate-600">{{ selected.body }}</p>
        <p v-if="selected.footer" class="mt-1 text-xs text-slate-400">{{ selected.footer }}</p>
        <div v-if="selected.buttons?.length" class="mt-2 flex flex-wrap gap-1.5">
          <span
            v-for="(button, buttonIndex) in selected.buttons"
            :key="`${buttonIndex}-${button.type}`"
            class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600"
          >
            {{ button.text || button.type }}
          </span>
        </div>
        <div v-if="selected.cards?.length" class="mt-3 flex gap-2 overflow-x-auto pb-1">
          <div
            v-for="(card, cardIndex) in selected.cards"
            :key="`card-${cardIndex}`"
            class="w-40 shrink-0 rounded-[8px] border border-slate-200 bg-white px-3 py-2"
          >
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
              {{ __('Card', textDomain) }} {{ cardIndex + 1 }} · {{ String(card.header_format || '').toLowerCase() }}
            </p>
            <p class="mt-1 whitespace-pre-line text-xs leading-4 text-slate-600">{{ card.body }}</p>
            <p v-if="card.buttons?.length" class="mt-1 text-[11px] text-slate-400">
              {{ card.buttons.map((button) => button.text || button.type).join(' · ') }}
            </p>
          </div>
        </div>
      </div>

      <FieldGroup
        v-if="Array.isArray(modelValue.variables) && modelValue.variables.length"
        :title="__('Variables', textDomain)"
        :description="__('Each template variable is filled at send time with the placeholder you map here.', textDomain)"
      >
        <div class="space-y-3">
          <div
            v-for="(variable, position) in modelValue.variables"
            :key="`${cardOf(variable)}-${variable.component}-${variable.index}-${variable.sub_type}-${variable.format}-${variable.key}`"
            class="flex items-end gap-2"
          >
            <div class="flex-1">
              <BaseTextFieldVariables
                :model-value="String(variable.value || '')"
                :label="variableLabel(variable)"
                :placeholder="variablePlaceholder(variable)"
                :placeholders="availablePlaceholders"
                @update:model-value="updateVariable(position, $event)"
              />
            </div>
            <button
              v-if="isMediaHeader(variable)"
              type="button"
              class="mb-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
              @click="openMediaLibrary(position, String(variable.format))"
            >
              <ImagePlus class="text-base" />
              {{ __('Library', textDomain) }}
            </button>
          </div>
        </div>
      </FieldGroup>
    </div>
  </div>
</template>
