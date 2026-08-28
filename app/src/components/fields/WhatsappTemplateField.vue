<script setup>

/**
 * WhatsappTemplateField.vue frontend component.
 *
 * Picks one of the Meta-approved message templates the account owns, instead of
 * asking for the template name to be typed from memory. Templates are authored
 * in the Joinotify panel, so this only reads the synced list and offers a manual
 * sync for a template that was just created or approved.
 *
 * `field.component_props.category` narrows the list — the OTP login code, for
 * instance, only accepts AUTHENTICATION templates. The language is not asked for
 * here: it belongs to the template and is resolved from the synced list when the
 * message is sent, because sending another language code is an error.
 *
 * @since 2.3.0
 */
import { computed, inject, onMounted, ref } from 'vue';
import SelectField from './SelectField.vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const api = inject('joinotifyApi', null);

const templates = ref([]);
const loading = ref(false);
const syncing = ref(false);
const errorMsg = ref('');
const loaded = ref(false);

const category = computed(() => String(props.field?.component_props?.category || '').toUpperCase());

/**
 * Templates that can actually be delivered, narrowed to the wanted category.
 *
 * Anything other than APPROVED is refused by Meta at send time, so offering it
 * here would only move the failure into the log.
 *
 * @since 2.3.0
 * @returns {Array<Object>} Selectable templates.
 */
const usable = computed(() =>
  templates.value.filter((template) => {
    if (String(template.status || '').toUpperCase() !== 'APPROVED') {
      return false;
    }

    return !category.value || String(template.category || '').toUpperCase() === category.value;
  })
);

const options = computed(() => {
  const list = usable.value.map((template) => ({
    value: template.name,
    label: template.language ? `${template.name} · ${template.language}` : template.name,
  }));

  // A name saved before the list loaded (or approved on another account) must not
  // vanish from the field just because it is not in the current listing.
  const current = String(props.modelValue || '');

  if (current && !list.some((option) => option.value === current)) {
    list.unshift({ value: current, label: `${current} — ${__('not found in the account', textDomain)}` });
  }

  return list;
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
 * Read the templates already synced from the account.
 *
 * @since 2.3.0
 * @param {boolean} [force] Ask the API to reconcile with Meta before answering.
 * @returns {Promise<void>}
 */
async function load(force = false) {
  if (!api) {
    return;
  }

  loading.value = true;
  errorMsg.value = '';

  try {
    const res = await api.get(`admin/builder/whatsapp-templates${force ? '?refresh=true' : ''}`);

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Could not load your message templates.', textDomain);
      return;
    }

    templates.value = Array.isArray(res?.templates) ? res.templates : [];
    loaded.value = true;
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}

/**
 * Ask the API to reconcile its mirror with Meta, then reload the list.
 *
 * @since 2.3.0
 * @returns {Promise<void>}
 */
async function sync() {
  if (!api || syncing.value) {
    return;
  }

  syncing.value = true;
  errorMsg.value = '';

  try {
    const res = await api.post('admin/builder/whatsapp-templates/sync', {});

    if (res?.status === 'error') {
      errorMsg.value = res.message || __('Could not sync your message templates.', textDomain);
      return;
    }

    templates.value = Array.isArray(res?.templates) ? res.templates : [];
    loaded.value = true;
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    syncing.value = false;
  }
}

onMounted(() => load());
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-stretch gap-2">
      <div class="min-w-0 flex-1">
        <SelectField
          :field="selectField"
          :name="name"
          :disabled="disabled || loading"
          :model-value="modelValue"
          @update:model-value="emit('update:modelValue', $event)"
        />
      </div>

      <button
        v-if="api"
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-[8px] border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="loading || syncing"
        :title="__('Sync templates with your account', textDomain)"
        @click="sync"
      >
        <svg
          class="h-4 w-4 shrink-0"
          :class="loading || syncing ? 'animate-spin' : ''"
          viewBox="0 0 20 20"
          fill="none"
          aria-hidden="true"
        >
          <path d="M15.5 5.5A6.5 6.5 0 1 0 16.9 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
          <path d="M16 3v3h-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>{{ syncing ? __('Syncing…', textDomain) : __('Sync', textDomain) }}</span>
      </button>
    </div>

    <p v-if="emptyMessage" class="text-xs text-amber-600">{{ emptyMessage }}</p>
    <p v-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>
  </div>
</template>
