<script setup>
/**
 * SourcesTab.vue — how the site's integrations fill the contact base.
 *
 * Switches the Joinotify Cloud sync and each of its sources on or off, sets
 * the tags every source adds, maps site data (user and order meta) to custom
 * fields, asks for marketing consent at checkout and sign-up, and configures,
 * form by form, which fields make the contact. The sync's monitor (queue,
 * pauses, "Send existing customers") is the same one shown in Settings.
 *
 * @since 2.5.0
 */
import { computed, onMounted, reactive, ref } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { useContactsContext } from '../context';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import CloudSyncStatusField from '../../../components/fields/CloudSyncStatusField.vue';
import ToggleSwitch from '../../../components/toggles/ToggleSwitch.vue';
import FormRuleEditor from '../components/FormRuleEditor.vue';

const { api, canWrite, definitions, toast, notifyError } = useContactsContext();

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const state = reactive({
  enabled: false,
  integrations: [],
  forms: [],
  attribute_sources: [],
  defaults: {},
});
const sync = reactive({});
const config = reactive({
  cloud_sync_forms_mode: 'all',
  cloud_sync_form_rules: {},
  cloud_sync_attribute_map: [],
});
const openForm = ref('');

const has = (integration) => state.integrations.includes(integration);

const sources = computed(() => [
  {
    id: 'woocommerce',
    available: has('woocommerce'),
    label: __('WooCommerce orders and customers', textDomain),
    help: __('Buyers of every order (with or without an account), their order history and their subscriptions.', textDomain),
  },
  {
    id: 'wordpress',
    available: true,
    label: __('WordPress users', textDomain),
    help: __('People who register on the site, and changes to their phone, name or e-mail.', textDomain),
  },
  {
    id: 'forms',
    available: has('wpforms') || has('elementor_pro'),
    label: __('Forms (WPForms and Elementor)', textDomain),
    help: __('Whoever sends a form with a phone or an e-mail, read through the rules below.', textDomain),
  },
  {
    id: 'carts',
    available: has('flexify_checkout'),
    label: __('Abandoned carts (Flexify Checkout)', textDomain),
    help: __('Leads collected at checkout and carts that were abandoned, recovered or lost.', textDomain),
  },
]);

const customFields = computed(() => definitions.activeFields.value);
const targetOptions = computed(() => [
  { label: __('— Pick a custom field —', textDomain), value: '' },
  ...customFields.value.map((field) => ({ label: field.label, value: field.key })),
]);
const kindOptions = computed(() => state.attribute_sources.map((source) => ({ label: source.label, value: source.value })));
const formsModeOptions = computed(() => [
  { label: __('Every form with a phone or an e-mail', textDomain), value: 'all' },
  { label: __('Only the forms switched on below', textDomain), value: 'selected' },
]);

function formKey(form) {
  return `${form.plugin}:${form.id}`;
}

function ensureRule(form) {
  const key = formKey(form);

  if (!config.cloud_sync_form_rules[key]) {
    config.cloud_sync_form_rules[key] = {
      enabled: config.cloud_sync_forms_mode === 'all',
      phone: '',
      email: '',
      first_name: '',
      last_name: '',
      consent: '',
      consent_text: '',
      tags: [],
      attributes: {},
    };
  }

  return config.cloud_sync_form_rules[key];
}

function toggleForm(form) {
  const key = formKey(form);

  if (openForm.value === key) {
    openForm.value = '';
    return;
  }

  ensureRule(form);
  openForm.value = key;
}

function formEnabled(form) {
  const rule = config.cloud_sync_form_rules[formKey(form)];

  return rule ? Boolean(rule.enabled) : config.cloud_sync_forms_mode === 'all';
}

// Map rows are edited as `{ kind: 'user_meta:', key }` and stored as `{ source: 'user_meta:key', target }`.
const mapRows = ref([]);

function toRows(map) {
  return (map || []).map((row) => {
    const [kind, ...rest] = String(row.source || '').split(':');

    return { kind: `${kind}:`, key: rest.join(':'), target: row.target || '' };
  });
}

function addMapRow() {
  mapRows.value.push({ kind: state.attribute_sources[0]?.value || 'user_meta:', key: '', target: '' });
}

function apply(response) {
  state.enabled = Boolean(response.enabled);
  state.integrations = response.integrations || [];
  state.forms = response.forms || [];
  state.attribute_sources = response.attribute_sources || [];
  state.defaults = response.defaults || {};
  Object.assign(sync, response.sync || {});
  config.cloud_sync_forms_mode = response.config?.cloud_sync_forms_mode || 'all';
  config.cloud_sync_form_rules = { ...(response.config?.cloud_sync_form_rules || {}) };
  ['woocommerce', 'wordpress', 'forms', 'carts'].forEach((source) => {
    config[`cloud_sync_tags_${source}`] = response.config?.[`cloud_sync_tags_${source}`] || '';
  });
  mapRows.value = toRows(response.config?.cloud_sync_attribute_map);
}

async function load() {
  loading.value = true;
  loadError.value = '';

  try {
    apply(await api.get('/admin/contacts/sources'));
  } catch (caught) {
    loadError.value = caught?.message || __('Could not load the sources.', textDomain);
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;

  try {
    const response = await api.post('/admin/contacts/sources/save', {
      sync: { ...sync },
      config: {
        ...config,
        cloud_sync_attribute_map: mapRows.value
          .filter((row) => row.key.trim() && row.target)
          .map((row) => ({ source: `${row.kind}${row.key.trim()}`, target: row.target })),
      },
    });

    apply(response);
    toast(response.message, 'success', __('Sources', textDomain));
  } catch (caught) {
    notifyError(caught);
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  load();
  definitions.load();
});
</script>

<template>
  <div class="mt-6 flex flex-col gap-6">
    <div v-if="loading" class="rounded-[8px] bg-white py-16 text-center text-[14px] text-slate-400 ring-1 ring-slate-100">{{ __('Loading…', textDomain) }}</div>

    <div v-else-if="loadError" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
      <p>{{ loadError }}</p>
      <BaseButton class="mt-3" size="sm" :title="__('Try again', textDomain)" variant="secondary" @click="load" />
    </div>

    <template v-else>
      <!-- The sync itself -->
      <section class="rounded-[8px] bg-white px-4 py-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 sm:px-6 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="max-w-2xl">
            <h2 class="text-[16px] font-semibold text-ink">{{ __('Joinotify Cloud sync', textDomain) }}</h2>
            <p class="mt-1 text-[13px] leading-6 text-slate-500">
              {{ __('Sends the customers, users and leads of this site to your contact base, and what they do here as events your flows can react to. Contacts are queued and sent in the background, never during a checkout.', textDomain) }}
            </p>
          </div>
          <ToggleSwitch v-model="sync.enable_cloud_sync" :disabled="!canWrite" :label="sync.enable_cloud_sync ? __('On', textDomain) : __('Off', textDomain)" />
        </div>

        <div v-if="state.enabled" class="mt-4">
          <CloudSyncStatusField />
        </div>
      </section>

      <!-- Sources -->
      <section class="rounded-[8px] bg-white px-4 py-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 sm:px-6 sm:py-6">
        <h2 class="text-[16px] font-semibold text-ink">{{ __('Where contacts come from', textDomain) }}</h2>
        <p class="mt-1 text-[13px] text-slate-500">{{ __('Each source can add its own tags, on top of the tag of this site.', textDomain) }}</p>

        <div class="mt-4 divide-y divide-slate-100">
          <div v-for="source in sources" :key="source.id" class="grid gap-3 py-4 lg:grid-cols-[1fr_320px]" :class="source.available ? '' : 'opacity-50'">
            <div class="flex items-start gap-3">
              <ToggleSwitch v-model="sync[`cloud_sync_${source.id}`]" :disabled="!source.available || !canWrite" />
              <div>
                <p class="text-[14px] font-medium text-slate-700">{{ source.label }}</p>
                <p class="text-[12px] text-slate-400">{{ source.available ? source.help : __('Not active on this site.', textDomain) }}</p>
              </div>
            </div>
            <label class="flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('Tags to add, separated by commas', textDomain) }}</span>
              <input v-model="config[`cloud_sync_tags_${source.id}`]" :class="inputClass" :disabled="!source.available" type="text" />
            </label>
          </div>
        </div>

        <div class="mt-2 grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-2">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Tag of this site', textDomain) }}</span>
            <input v-model="sync.cloud_sync_source_tag" :class="inputClass" maxlength="60" :placeholder="state.defaults.source_tag" type="text" />
            <span class="text-[12px] text-slate-400">{{ __('Every contact sent by this site carries it. Empty uses the site name.', textDomain) }}</span>
          </label>
          <div class="flex flex-col gap-3">
            <ToggleSwitch v-model="sync.cloud_sync_send_items" :label="__('Send the items of each order', textDomain)" />
            <ToggleSwitch v-model="sync.cloud_sync_send_address" :label="__('Send full addresses (city and state are always sent)', textDomain)" />
          </div>
        </div>
      </section>

      <!-- Consent -->
      <section class="rounded-[8px] bg-white px-4 py-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 sm:px-6 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="max-w-2xl">
            <h2 class="text-[16px] font-semibold text-ink">{{ __('Marketing consent', textDomain) }}</h2>
            <p class="mt-1 text-[13px] leading-6 text-slate-500">
              {{ __('Adds an unticked checkbox to the checkout, the sign-up forms and My account. Who ticks it is opted in with the checkbox text as evidence; who unticks it in My account is opted out.', textDomain) }}
            </p>
          </div>
          <ToggleSwitch v-model="sync.cloud_sync_consent_checkbox" />
        </div>
        <label v-if="sync.cloud_sync_consent_checkbox" class="mt-4 flex flex-col gap-1">
          <span class="text-[12px] font-medium text-slate-500">{{ __('Checkbox text', textDomain) }}</span>
          <input v-model="sync.cloud_sync_consent_text" :class="inputClass" :placeholder="state.defaults.consent_text" type="text" />
        </label>
      </section>

      <!-- Site data to custom fields -->
      <section class="rounded-[8px] bg-white px-4 py-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 sm:px-6 sm:py-6">
        <h2 class="text-[16px] font-semibold text-ink">{{ __('Site data in custom fields', textDomain) }}</h2>
        <p class="mt-1 text-[13px] text-slate-500">
          {{ __('Sends a user or order meta (a CPF, a birthday, a checkout field) into a custom field of the contact. It only fills fields that are empty on Joinotify: what someone typed in the panel wins.', textDomain) }}
        </p>

        <p v-if="!customFields.length" class="mt-4 text-[13px] text-slate-400">{{ __('Create custom fields in the Fields & tags tab to map site data to them.', textDomain) }}</p>

        <div v-else class="mt-4 flex flex-col gap-2">
          <div v-for="(row, index) in mapRows" :key="index" class="grid items-center gap-2 lg:grid-cols-[220px_1fr_1fr_auto]">
            <BaseListboxSelect v-model="row.kind" :options="kindOptions" />
            <input v-model="row.key" :class="inputClass" :placeholder="__('Meta key, e.g. _billing_cpf', textDomain)" type="text" />
            <BaseListboxSelect v-model="row.target" :options="targetOptions" />
            <button type="button" class="rounded-[8px] px-2 py-1 text-[13px] text-rose-600 hover:bg-rose-50" @click="mapRows.splice(index, 1)">{{ __('Remove', textDomain) }}</button>
          </div>
          <div>
            <BaseButton size="sm" :title="__('Add mapping', textDomain)" variant="secondary" @click="addMapRow" />
          </div>
        </div>
      </section>

      <!-- Forms -->
      <section v-if="has('wpforms') || has('elementor_pro')" class="rounded-[8px] bg-white px-4 py-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100 sm:px-6 sm:py-6">
        <h2 class="text-[16px] font-semibold text-ink">{{ __('Forms', textDomain) }}</h2>
        <p class="mt-1 text-[13px] text-slate-500">
          {{ __('Choose which forms create contacts and which of their fields are the phone, e-mail, name, consent and custom fields. A form without a rule is read by the field types and labels.', textDomain) }}
        </p>

        <div class="mt-4 max-w-md">
          <BaseListboxSelect v-model="config.cloud_sync_forms_mode" :options="formsModeOptions" />
        </div>

        <ul v-if="state.forms.length" class="mt-4 divide-y divide-slate-100">
          <li v-for="form in state.forms" :key="formKey(form)" class="py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-[14px] font-medium text-slate-700">{{ form.title || form.id }}</p>
                <p class="text-[12px] text-slate-400">
                  {{ form.plugin === 'wpforms' ? 'WPForms' : 'Elementor' }} ·
                  {{ formEnabled(form) ? __('Creates contacts', textDomain) : __('Ignored', textDomain) }}
                </p>
              </div>
              <button
                type="button"
                class="rounded-[8px] border border-slate-200 px-3 py-1.5 text-[13px] font-semibold text-slate-600 hover:bg-slate-50"
                @click="toggleForm(form)"
              >
                {{ openForm === formKey(form) ? __('Close', textDomain) : __('Configure', textDomain) }}
              </button>
            </div>
            <FormRuleEditor
              v-if="openForm === formKey(form) && config.cloud_sync_form_rules[formKey(form)]"
              class="mt-3"
              :custom-fields="customFields"
              :form="form"
              :rule="config.cloud_sync_form_rules[formKey(form)]"
            />
          </li>
        </ul>
        <p v-else class="mt-4 text-[13px] text-slate-400">{{ __('No form was found on this site.', textDomain) }}</p>
      </section>

      <div v-if="canWrite" class="sticky bottom-4 flex justify-end">
        <BaseButton :loading="saving" :title="__('Save sources', textDomain)" @click="save" />
      </div>
    </template>
  </div>
</template>
