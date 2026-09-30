<script setup>
/**
 * ContactFormModal.vue — adds a contact by hand or edits one.
 *
 * A new contact can be created already opted in, with the evidence of how the
 * consent was obtained; an edit never changes consent (that has its own
 * actions). When the phone already belongs to another contact, the dialog
 * offers to open that contact or to update it with what was typed.
 *
 * @since 2.5.0
 */
import { computed, reactive, ref, watch } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { CloudRequestError } from '../../../services/contactsApi';
import { useContactsContext, errorMessage } from '../context';
import BaseButton from '../../../components/base/BaseButton.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';
import PhoneField from '../../../components/fields/PhoneField.vue';
import AttributeInput from './AttributeInput.vue';
import TagPicker from './TagPicker.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  contact: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved', 'open-contact']);

const { api, bootstrap, definitions, toast } = useContactsContext();

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const form = reactive({
  phone: '',
  firstName: '',
  lastName: '',
  email: '',
  attributes: {},
  tagIds: [],
  optIn: false,
  evidence: '',
});

const saving = ref(false);
const error = ref('');
const fieldErrors = ref({});
const existingId = ref('');

const isEdit = computed(() => Boolean(props.contact?.id));
const defaultCountry = computed(() => String(bootstrap.default_country || 'us').toLowerCase());
const phoneField = computed(() => ({ label: __('Phone', textDomain), placeholder: __('Phone with country code', textDomain) }));

function reset() {
  const contact = props.contact || {};

  form.phone = contact.phone ? `+${String(contact.phone).replace(/^\+/, '')}` : '';
  form.firstName = contact.firstName || '';
  form.lastName = contact.lastName || '';
  form.email = contact.email || '';
  form.attributes = { ...(contact.attributes || {}) };
  form.tagIds = (contact.tags || []).map((tag) => tag.id);
  form.optIn = false;
  form.evidence = '';
  error.value = '';
  fieldErrors.value = {};
  existingId.value = '';
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      reset();
      definitions.load();
    }
  },
  { immediate: true }
);

function payload() {
  const contact = {
    phone: form.phone,
    firstName: form.firstName,
    lastName: form.lastName,
    email: form.email,
    tagIds: form.tagIds,
    attributes: {},
  };

  // Only the fields on screen travel: an archived field keeps its value.
  definitions.activeFields.value.forEach((field) => {
    const value = form.attributes[field.key];
    contact.attributes[field.key] = value === undefined || value === null ? '' : value;
  });

  if (!isEdit.value) {
    contact.defaultCountry = bootstrap.default_country || undefined;

    if (form.optIn) {
      contact.optIn = { evidence: form.evidence };
    }
  }

  return contact;
}

async function submit(upsert = false) {
  error.value = '';
  fieldErrors.value = {};
  existingId.value = '';

  if (!isEdit.value && !form.phone.trim()) {
    fieldErrors.value = { phone: __('Type the phone number of the contact.', textDomain) };
    return;
  }

  if (!isEdit.value && form.optIn && form.evidence.trim().length < 3) {
    fieldErrors.value = { evidence: __('Describe how the contact gave consent.', textDomain) };
    return;
  }

  saving.value = true;

  try {
    const response = await api.saveContact({ id: props.contact?.id || '', contact: payload(), upsert });

    toast(response.message, 'success', __('Contacts', textDomain));
    emit('saved', response.contact);
  } catch (caught) {
    if (caught instanceof CloudRequestError) {
      if (caught.type === 'contact_exists') {
        existingId.value = caught.contactId;
      }

      const errors = {};
      caught.issues.forEach((issue) => {
        const key = issue.field.startsWith('attributes.') ? issue.field : issue.field.split('.')[0];
        errors[key || 'form'] = issue.message;
      });
      fieldErrors.value = errors;
    }

    error.value = errorMessage(caught);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <!-- Teleported like the drawer, so it opens above it; the class keeps the screen's scoped styles (phone field). -->
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog
        :close-on-backdrop="!saving"
        :open="open"
        size-class="max-w-3xl"
        :title="isEdit ? __('Edit contact', textDomain) : __('Add contact', textDomain)"
        @close="$emit('close')"
      >
        <form class="flex flex-col gap-5" @submit.prevent="submit(false)">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <PhoneField
                v-model="form.phone"
                :default-country="defaultCountry"
                :field="phoneField"
                :locale="bootstrap.locale || 'en'"
                name="joinotify-contact-phone"
              />
              <p v-if="fieldErrors.phone" class="mt-1 text-[12px] text-danger">{{ fieldErrors.phone }}</p>
            </div>

            <label class="flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('First name', textDomain) }}</span>
              <input v-model="form.firstName" :class="inputClass" maxlength="80" type="text" />
              <span v-if="fieldErrors.firstName" class="text-[12px] text-danger">{{ fieldErrors.firstName }}</span>
            </label>

            <label class="flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('Last name', textDomain) }}</span>
              <input v-model="form.lastName" :class="inputClass" maxlength="80" type="text" />
              <span v-if="fieldErrors.lastName" class="text-[12px] text-danger">{{ fieldErrors.lastName }}</span>
            </label>

            <label class="flex flex-col gap-1 sm:col-span-2">
              <span class="text-[12px] font-medium text-slate-500">{{ __('E-mail', textDomain) }}</span>
              <input v-model="form.email" :class="inputClass" maxlength="254" type="email" />
              <span v-if="fieldErrors.email" class="text-[12px] text-danger">{{ fieldErrors.email }}</span>
            </label>
          </div>

          <div class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Tags', textDomain) }}</span>
            <TagPicker v-model="form.tagIds" :tags="definitions.sortedTags.value" />
            <span v-if="isEdit" class="text-[12px] text-slate-400">{{ __('The contact keeps exactly the tags listed here.', textDomain) }}</span>
          </div>

          <div v-if="definitions.activeFields.value.length" class="flex flex-col gap-3">
            <p class="text-[13px] font-semibold text-slate-600">{{ __('Custom fields', textDomain) }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
              <AttributeInput
                v-for="field in definitions.activeFields.value"
                :key="field.id"
                v-model="form.attributes[field.key]"
                :error="fieldErrors[`attributes.${field.key}`] || ''"
                :field="field"
              />
            </div>
          </div>

          <div v-if="!isEdit" class="rounded-[8px] border border-slate-200 p-4">
            <label class="flex items-start gap-3 text-[14px] text-slate-700">
              <input v-model="form.optIn" class="mt-1" type="checkbox" />
              <span>
                {{ __('The contact agreed to receive marketing messages', textDomain) }}
                <span class="block text-[12px] text-slate-400">{{ __('Leave it unticked when you do not have that consent: the contact still receives the messages of your workflows, but not campaigns.', textDomain) }}</span>
              </span>
            </label>
            <label v-if="form.optIn" class="mt-3 flex flex-col gap-1">
              <span class="text-[12px] font-medium text-slate-500">{{ __('How was the consent obtained?', textDomain) }}</span>
              <textarea
                v-model="form.evidence"
                :class="inputClass"
                maxlength="500"
                :placeholder="__('e.g. Sign-up form at the store counter, 29/09/2026', textDomain)"
                rows="2"
              />
              <span v-if="fieldErrors.evidence" class="text-[12px] text-danger">{{ fieldErrors.evidence }}</span>
            </label>
          </div>

          <div v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">
            <p>{{ error }}</p>
            <div v-if="existingId" class="mt-3 flex flex-wrap gap-2">
              <BaseButton :title="__('Open the existing contact', textDomain)" size="sm" variant="secondary" @click="$emit('open-contact', existingId)" />
              <BaseButton :loading="saving" :title="__('Update it with these data', textDomain)" size="sm" @click="submit(true)" />
            </div>
            <p v-if="existingId" class="mt-2 text-[12px]">
              {{ __('Updating replaces the tags of the existing contact and leaves its consent as it is.', textDomain) }}
            </p>
          </div>

          <div class="flex justify-end gap-3">
            <BaseButton :disabled="saving" :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('close')" />
            <BaseButton :loading="saving" :title="isEdit ? __('Save changes', textDomain) : __('Add contact', textDomain)" type="submit" />
          </div>
        </form>
      </ModalDialog>
    </div>
  </Teleport>
</template>
