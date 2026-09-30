<script setup>
/**
 * ContactDrawer.vue — one contact: its data, tags, custom fields, consent,
 * suppression rows, the site's users that are the same person and its
 * history, with the actions that change it (edit, opt-in/opt-out, export,
 * erase).
 *
 * @since 2.5.0
 */
import { computed, ref, watch } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { downloadJson } from '../../../utils/downloadJson';
import { useContactsContext } from '../context';
import {
  activityLabel,
  consentBadgeClass,
  consentOptions,
  formatDate,
  formatPhone,
  labelOf,
  sourceOptions,
} from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseDrawer from '../../../components/base/BaseDrawer.vue';
import ConfirmActionModal from '../../../components/workflows/ConfirmActionModal.vue';
import ConsentModal from './ConsentModal.vue';
import TagChip from './TagChip.vue';

const props = defineProps({
  contactId: { type: String, default: '' },
});

const emit = defineEmits(['close', 'edit', 'changed', 'deleted']);

const { api, bootstrap, canWrite, definitions, toast, notifyError } = useContactsContext();

const contact = ref(null);
const wpUsers = ref([]);
const loading = ref(false);
const error = ref('');
const view = ref('details');
const activity = ref([]);
const activityPage = ref(0);
const activityTotalPages = ref(1);
const activityLoading = ref(false);
const consentMode = ref('');
const consentSaving = ref(false);
const confirmDelete = ref(false);
const deleting = ref(false);
const exporting = ref(false);

const locale = computed(() => bootstrap.locale || '');
const displayName = computed(() => contact.value?.name || [contact.value?.firstName, contact.value?.lastName].filter(Boolean).join(' ') || contact.value?.profileName || formatPhone(contact.value?.phone));

const attributes = computed(() => {
  const values = contact.value?.attributes || {};

  return definitions.fields.value
    .filter((field) => values[field.key] !== undefined && values[field.key] !== null && values[field.key] !== '')
    .map((field) => ({ key: field.key, label: field.label, value: formatAttribute(field, values[field.key]) }));
});

function formatAttribute(field, value) {
  if (Array.isArray(value)) {
    return value.join(', ');
  }

  if (typeof value === 'boolean') {
    return value ? __('Yes', textDomain) : __('No', textDomain);
  }

  if (field.type === 'datetime') {
    return formatDate(value, locale.value);
  }

  return String(value);
}

async function load() {
  if (!props.contactId) {
    return;
  }

  loading.value = true;
  error.value = '';
  view.value = 'details';
  activity.value = [];
  activityPage.value = 0;

  try {
    const response = await api.contact(props.contactId);

    contact.value = response.contact || null;
    wpUsers.value = Array.isArray(response.wp_users) ? response.wp_users : [];
    definitions.load();
  } catch (caught) {
    contact.value = null;
    error.value = caught?.message || __('Could not load the contact.', textDomain);
  } finally {
    loading.value = false;
  }
}

async function loadActivity() {
  activityLoading.value = true;

  try {
    const response = await api.activity(props.contactId, activityPage.value + 1);

    activity.value = [...activity.value, ...(response.items || [])];
    activityPage.value = response.pagination?.current_page || activityPage.value + 1;
    activityTotalPages.value = response.pagination?.total_pages || 1;
  } catch (caught) {
    notifyError(caught);
  } finally {
    activityLoading.value = false;
  }
}

function showActivity() {
  view.value = 'activity';

  if (!activityPage.value) {
    loadActivity();
  }
}

async function saveConsent(text) {
  consentSaving.value = true;

  try {
    const response = await api.consent(props.contactId, consentMode.value, text);

    toast(response.message, 'success', __('Contacts', textDomain));
    consentMode.value = '';
    await load();
    emit('changed', contact.value);
  } catch (caught) {
    notifyError(caught);
  } finally {
    consentSaving.value = false;
  }
}

async function exportData() {
  exporting.value = true;

  try {
    const response = await api.exportContact(props.contactId);

    downloadJson(response.content, response.filename);
  } catch (caught) {
    notifyError(caught);
  } finally {
    exporting.value = false;
  }
}

async function erase() {
  deleting.value = true;

  try {
    const response = await api.deleteContact(props.contactId);

    toast(response.message, 'success', __('Contacts', textDomain));
    confirmDelete.value = false;
    emit('deleted', props.contactId);
  } catch (caught) {
    notifyError(caught);
  } finally {
    deleting.value = false;
  }
}

watch(() => props.contactId, load, { immediate: true });

defineExpose({ reload: load });
</script>

<template>
  <BaseDrawer :open="Boolean(contactId)" :title="contact ? displayName : __('Contact', textDomain)" @close="$emit('close')">
    <div v-if="loading" class="py-16 text-center text-[14px] text-slate-400">{{ __('Loading…', textDomain) }}</div>

    <div v-else-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
      <p>{{ error }}</p>
      <BaseButton class="mt-3" size="sm" :title="__('Try again', textDomain)" variant="secondary" @click="load" />
    </div>

    <div v-else-if="contact" class="flex flex-col gap-5 text-[14px] text-slate-700">
      <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-medium ring-1 ring-inset" :class="consentBadgeClass(contact.optInStatus)">
          {{ labelOf(consentOptions(), contact.optInStatus) }}
        </span>
        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[12px] text-slate-600">{{ labelOf(sourceOptions(), contact.source) }}</span>
      </div>

      <div v-if="canWrite" class="flex flex-wrap gap-2">
        <BaseButton size="sm" :title="__('Edit', textDomain)" @click="$emit('edit', contact)" />
        <BaseButton
          v-if="contact.optInStatus !== 'opted_in'"
          size="sm"
          :title="__('Record opt-in', textDomain)"
          variant="secondary"
          @click="consentMode = 'opt_in'"
        />
        <BaseButton
          v-if="contact.optInStatus !== 'opted_out'"
          size="sm"
          :title="__('Record opt-out', textDomain)"
          variant="secondary"
          @click="consentMode = 'opt_out'"
        />
        <BaseButton :loading="exporting" size="sm" :title="__('Export data', textDomain)" variant="ghost" @click="exportData" />
        <BaseButton size="sm" :title="__('Erase', textDomain)" variant="danger" @click="confirmDelete = true" />
      </div>

      <div class="flex gap-2 border-b border-slate-100">
        <button
          type="button"
          class="-mb-px border-b-2 px-3 py-2 text-[13px] font-medium"
          :class="view === 'details' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
          @click="view = 'details'"
        >
          {{ __('Details', textDomain) }}
        </button>
        <button
          type="button"
          class="-mb-px border-b-2 px-3 py-2 text-[13px] font-medium"
          :class="view === 'activity' ? 'border-primary-600 text-primary-700' : 'border-transparent text-slate-500'"
          @click="showActivity"
        >
          {{ __('History', textDomain) }}
        </button>
      </div>

      <template v-if="view === 'details'">
        <dl class="grid grid-cols-[130px_1fr] gap-x-3 gap-y-2">
          <dt class="text-slate-400">{{ __('Phone', textDomain) }}</dt>
          <dd>{{ formatPhone(contact.phone) }}</dd>
          <dt class="text-slate-400">{{ __('E-mail', textDomain) }}</dt>
          <dd class="break-all">{{ contact.email || '—' }}</dd>
          <template v-if="contact.profileName">
            <dt class="text-slate-400">{{ __('WhatsApp name', textDomain) }}</dt>
            <dd>{{ contact.profileName }}</dd>
          </template>
          <dt class="text-slate-400">{{ __('Added', textDomain) }}</dt>
          <dd>{{ formatDate(contact.createdAt, locale) }}</dd>
          <dt class="text-slate-400">{{ __('Last message received', textDomain) }}</dt>
          <dd>{{ formatDate(contact.lastInboundAt, locale) }}</dd>
          <dt class="text-slate-400">{{ __('Last message sent', textDomain) }}</dt>
          <dd>{{ formatDate(contact.lastOutboundAt, locale) }}</dd>
        </dl>

        <section>
          <h4 class="mb-2 text-[13px] font-semibold text-slate-600">{{ __('Consent', textDomain) }}</h4>
          <p v-if="contact.optInStatus === 'opted_in'" class="text-[13px] text-slate-600">
            {{ formatDate(contact.optInAt, locale) }}<template v-if="contact.optInEvidence"> — {{ contact.optInEvidence }}</template>
          </p>
          <p v-else-if="contact.optInStatus === 'opted_out'" class="text-[13px] text-slate-600">
            {{ formatDate(contact.optOutAt, locale) }}<template v-if="contact.optOutReason"> — {{ contact.optOutReason }}</template>
          </p>
          <p v-else class="text-[13px] text-slate-400">{{ __('No consent recorded for marketing messages.', textDomain) }}</p>
          <ul v-if="contact.suppressions && contact.suppressions.length" class="mt-2 space-y-1">
            <li v-for="row in contact.suppressions" :key="row.id" class="rounded-[8px] bg-rose-50 px-3 py-2 text-[12px] text-rose-700">
              {{ __('On the suppression list', textDomain) }}: {{ row.reason }} · {{ formatDate(row.createdAt, locale) }}
            </li>
          </ul>
        </section>

        <section>
          <h4 class="mb-2 text-[13px] font-semibold text-slate-600">{{ __('Tags', textDomain) }}</h4>
          <div v-if="contact.tags && contact.tags.length" class="flex flex-wrap gap-1.5">
            <TagChip v-for="tag in contact.tags" :key="tag.id" :color="tag.color || ''" :name="tag.name" />
          </div>
          <p v-else class="text-[13px] text-slate-400">{{ __('No tags.', textDomain) }}</p>
        </section>

        <section v-if="attributes.length">
          <h4 class="mb-2 text-[13px] font-semibold text-slate-600">{{ __('Custom fields', textDomain) }}</h4>
          <dl class="grid grid-cols-[130px_1fr] gap-x-3 gap-y-2">
            <template v-for="item in attributes" :key="item.key">
              <dt class="truncate text-slate-400" :title="item.label">{{ item.label }}</dt>
              <dd class="break-words">{{ item.value }}</dd>
            </template>
          </dl>
        </section>

        <section>
          <h4 class="mb-2 text-[13px] font-semibold text-slate-600">{{ __('On this site', textDomain) }}</h4>
          <ul v-if="wpUsers.length" class="space-y-1">
            <li v-for="user in wpUsers" :key="user.id">
              <a :href="user.edit_url" class="font-medium text-primary-700 hover:underline">{{ user.name }}</a>
              <span class="ml-1 text-[12px] text-slate-400">{{ user.email }}</span>
            </li>
          </ul>
          <p v-else class="text-[13px] text-slate-400">{{ __('No user of this site has this e-mail or phone.', textDomain) }}</p>
        </section>
      </template>

      <template v-else>
        <ol class="space-y-3">
          <li v-for="entry in activity" :key="entry.id" class="border-l-2 border-slate-100 pl-3">
            <p class="font-medium text-slate-700">{{ activityLabel(entry.type) }}</p>
            <p class="text-[12px] text-slate-400">
              {{ formatDate(entry.createdAt, locale) }}<template v-if="entry.actor"> · {{ entry.actor }}</template>
            </p>
          </li>
        </ol>
        <p v-if="!activityLoading && !activity.length" class="text-[13px] text-slate-400">{{ __('Nothing recorded yet.', textDomain) }}</p>
        <BaseButton
          v-if="activityPage && activityPage < activityTotalPages"
          :loading="activityLoading"
          size="sm"
          :title="__('Load more', textDomain)"
          variant="secondary"
          @click="loadActivity"
        />
        <p v-if="activityLoading && !activity.length" class="text-[13px] text-slate-400">{{ __('Loading…', textDomain) }}</p>
      </template>
    </div>
  </BaseDrawer>

  <ConsentModal
    :loading="consentSaving"
    :mode="consentMode || 'opt_in'"
    :open="Boolean(consentMode)"
    @cancel="consentMode = ''"
    @confirm="saveConsent"
  />

  <ConfirmActionModal
    :confirm-label="__('Erase for good', textDomain)"
    :description="__('The contact is erased from your Joinotify account with its conversations, messages, tags and history. Its suppression rows stay, without the phone, and campaign recipients are anonymized. This is the erasure request of the LGPD and GDPR and cannot be undone.', textDomain)"
    :loading="deleting"
    :open="confirmDelete"
    :title="__('Erase this contact?', textDomain)"
    @cancel="confirmDelete = false"
    @confirm="erase"
  />
</template>
