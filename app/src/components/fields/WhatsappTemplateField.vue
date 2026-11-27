<script setup>

/**
 * WhatsappTemplateField.vue frontend component.
 *
 * Picks one of the Meta-approved message templates the account owns, instead of
 * asking for the template name to be typed from memory. Templates are authored
 * in the Joinotify panel, so this only reads the synced list and offers a manual
 * sync for a template that was just created or approved.
 *
 * `field.component_props` shapes the list:
 * - `category` narrows it: the OTP login code only accepts AUTHENTICATION
 *   templates.
 * - `senders` (`{ phone, waba_id }`, the first one being the default sender)
 *   and `sender_setting` (the setting that holds the chosen sender) make it the
 *   list of the sender's WhatsApp Business Account: Meta looks a template up on
 *   the account of the number that sends it.
 * - `language_setting` names the setting that receives the language of the
 *   picked template, since a template goes out in the language it was approved
 *   in and the same name can be approved in several. Each option is then a
 *   name in one language.
 *
 * The other settings are read and written through the `joinotifySettingsForm`
 * context of the integration modal; without it the field lists the default
 * account and stores the name only.
 *
 * @since 2.3.0
 * @version 2.8.0
 */
import { computed, inject, onMounted, watch } from 'vue';
import SelectField from './SelectField.vue';
import { __, textDomain } from '../../utils/i18n';
import { RefreshCw } from '@boxicons/vue';
import { useWhatsappTemplates } from '../../composables/useWhatsappTemplates';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const api = inject('joinotifyApi', null);
const form = inject('joinotifySettingsForm', null);

const componentProps = computed(() => props.field?.component_props || {});
const category = computed(() => String(componentProps.value.category || '').toUpperCase());
const languageSetting = computed(() => (form ? String(componentProps.value.language_setting || '') : ''));

/**
 * Account of the sender the template will go out from: the one chosen in the
 * sender setting, or the first sender when it is left on the default.
 *
 * @since 2.8.0
 * @returns {string}
 */
const senderWaba = computed(() => {
  const senders = Array.isArray(componentProps.value.senders) ? componentProps.value.senders : [];
  const settingKey = String(componentProps.value.sender_setting || '');
  const chosen = form && settingKey ? String(form.get(settingKey) || '') : '';
  const sender = senders.find((item) => item?.phone === chosen) || senders[0];

  return String(sender?.waba_id || '');
});

const { templates, loading, loaded, syncing, errorMsg, loadTemplates, syncTemplates } = useWhatsappTemplates(api, {
  approvedOnly: true,
  wabaId: () => senderWaba.value,
});

const currentName = computed(() => String(props.modelValue || ''));
const currentLanguage = computed(() => (languageSetting.value ? String(form.get(languageSetting.value) || '') : ''));

/**
 * Templates that can actually be delivered, narrowed to the wanted category.
 *
 * Anything other than APPROVED is refused by Meta at send time, so offering it
 * here would only move the failure into the log.
 *
 * @since 2.3.0
 * @version 2.8.0
 * @returns {Array<Object>} Selectable templates.
 */
const usable = computed(() =>
  templates.value.filter((template) => !category.value || String(template.category || '').toUpperCase() === category.value)
);

/**
 * Option value of a template: name and language when the language is stored
 * too, the name alone otherwise.
 *
 * @since 2.8.0
 * @param {Object} template Template from the list.
 * @returns {string}
 */
function keyOf(template) {
  return languageSetting.value ? `${template.name}|${template.language || ''}` : template.name;
}

// The stored name and language pick the template; a template saved before the
// language was stored falls back to the first one with that name.
const selected = computed(() =>
  usable.value.find((template) => template.name === currentName.value && template.language === currentLanguage.value)
  || usable.value.find((template) => template.name === currentName.value)
  || null
);

// A saved template the sender's account does not have would be refused by Meta.
const missing = computed(() => Boolean(currentName.value) && loaded.value && !loading.value && !errorMsg.value && !selected.value);

const options = computed(() => {
  const list = usable.value.map((template) => ({
    value: keyOf(template),
    label: template.language ? `${template.name} · ${template.language}` : template.name,
  }));

  // A name saved before the list loaded (or approved on another account) must not
  // vanish from the field just because it is not in the current listing.
  if (missing.value) {
    const value = languageSetting.value ? `${currentName.value}|${currentLanguage.value}` : currentName.value;
    const label = currentLanguage.value ? `${currentName.value} · ${currentLanguage.value}` : currentName.value;

    list.unshift({ value, label: `${label} — ${__('not found in the account', textDomain)}` });
  }

  return list;
});

const selectedValue = computed(() => {
  if (selected.value) {
    return keyOf(selected.value);
  }

  if (!currentName.value) {
    return '';
  }

  return languageSetting.value ? `${currentName.value}|${currentLanguage.value}` : currentName.value;
});

const selectField = computed(() => ({
  ...props.field,
  options: options.value,
  searchable: true,
}));

const emptyMessage = computed(() => {
  if (loading.value || !loaded.value || usable.value.length) {
    return '';
  }

  return category.value
    ? __('No approved template of this type was found on your account. Create it in the Joinotify panel and sync again.', textDomain)
    : __('No approved template was found on your account. Create it in the Joinotify panel and sync again.', textDomain);
});

/**
 * Store the picked template, and its language in the language setting.
 *
 * @since 2.8.0
 * @param {string} value Option value.
 * @returns {void}
 */
function pick(value) {
  const template = usable.value.find((item) => keyOf(item) === String(value));

  if (!template) {
    return;
  }

  emit('update:modelValue', template.name);

  if (languageSetting.value && template.language) {
    form.update(languageSetting.value, template.language);
  }
}

// Another sender may belong to another account, with other templates.
watch(senderWaba, (wabaId, previous) => {
  if (wabaId !== previous) {
    loadTemplates();
  }
});

onMounted(() => loadTemplates());
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-stretch gap-2">
      <div class="min-w-0 flex-1">
        <SelectField
          :field="selectField"
          :name="name"
          :disabled="disabled || loading"
          :model-value="selectedValue"
          @update:model-value="pick"
        />
      </div>

      <button
        v-if="api"
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-[8px] border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="loading || syncing"
        :title="__('Sync templates with your account', textDomain)"
        @click="syncTemplates"
      >
        <RefreshCw
          class="h-4 w-4 shrink-0"
          :class="loading || syncing ? 'animate-spin' : ''"
          aria-hidden="true"
        />
        <span>{{ syncing ? __('Syncing…', textDomain) : __('Sync', textDomain) }}</span>
      </button>
    </div>

    <p v-if="missing" class="text-xs text-amber-600">
      {{ __('The WhatsApp account of this sender does not have this template, so WhatsApp would refuse it. Choose a template from this list or another sender.', textDomain) }}
    </p>
    <p v-if="emptyMessage" class="text-xs text-amber-600">{{ emptyMessage }}</p>
    <p v-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>
  </div>
</template>
