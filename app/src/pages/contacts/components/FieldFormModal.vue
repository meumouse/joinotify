<script setup>
/**
 * FieldFormModal.vue — creates a custom field or edits one.
 *
 * The key and the type are fixed once the field exists, so an edit only
 * changes the label, the options of a choice field and the position. The key
 * is suggested from the label while the admin has not typed one.
 *
 * @since 2.5.0
 */
import { computed, reactive, ref, watch } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { useContactsContext, errorMessage } from '../context';
import { fieldTypeOptions } from '../labels';
import BaseButton from '../../../components/base/BaseButton.vue';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';
import ModalDialog from '../../../components/modals/ModalDialog.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  field: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const { api, toast } = useContactsContext();

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none disabled:bg-slate-50 disabled:text-slate-400';

const form = reactive({ label: '', key: '', type: 'text', options: '', position: 0 });
const keyTouched = ref(false);
const saving = ref(false);
const error = ref('');

const isEdit = computed(() => Boolean(props.field?.id));
const isChoice = computed(() => form.type === 'select' || form.type === 'multi_select');
const keyValid = computed(() => /^[a-z][a-z0-9_]{0,39}$/.test(form.key));

function suggestKey(label) {
  return label
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^[^a-z]+/, '')
    .replace(/_+$/, '')
    .slice(0, 40);
}

watch(
  () => props.open,
  (open) => {
    if (!open) {
      return;
    }

    form.label = props.field?.label || '';
    form.key = props.field?.key || '';
    form.type = props.field?.type || 'text';
    form.options = (props.field?.options || []).join('\n');
    form.position = props.field?.position ?? 0;
    keyTouched.value = isEdit.value;
    error.value = '';
  },
  { immediate: true }
);

watch(
  () => form.label,
  (label) => {
    if (!keyTouched.value) {
      form.key = suggestKey(label);
    }
  }
);

async function submit() {
  error.value = '';

  if (!isEdit.value && !keyValid.value) {
    error.value = __('The key starts with a lowercase letter and holds only lowercase letters, digits and underscores, up to 40 characters.', textDomain);
    return;
  }

  const options = form.options
    .split('\n')
    .map((option) => option.trim())
    .filter(Boolean);

  if (isChoice.value && !options.length) {
    error.value = __('A choice field needs at least one option.', textDomain);
    return;
  }

  saving.value = true;

  try {
    const response = await api.saveField(props.field?.id || '', {
      label: form.label,
      key: form.key,
      type: form.type,
      options: isChoice.value ? options : undefined,
      position: Number(form.position) || 0,
    });

    toast(response.message, 'success', __('Custom fields', textDomain));
    emit('saved', response.field);
  } catch (caught) {
    error.value = errorMessage(caught);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="joinotify-settings">
      <ModalDialog
        :close-on-backdrop="!saving"
        :open="open"
        size-class="max-w-xl"
        :title="isEdit ? __('Edit custom field', textDomain) : __('New custom field', textDomain)"
        @close="$emit('close')"
      >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Label', textDomain) }}</span>
            <input v-model="form.label" :class="inputClass" maxlength="80" required type="text" />
          </label>

          <label class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Key', textDomain) }}</span>
            <input
              v-model="form.key"
              :class="inputClass"
              :disabled="isEdit"
              maxlength="40"
              type="text"
              @input="keyTouched = true"
            />
            <span class="text-[12px] text-slate-400">
              {{ __('Identifies the field in audience filters, imports and the API. It cannot change after the field is created.', textDomain) }}
            </span>
          </label>

          <div class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Type', textDomain) }}</span>
            <BaseListboxSelect v-model="form.type" :disabled="isEdit" :options="fieldTypeOptions()" />
          </div>

          <label v-if="isChoice" class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Options, one per line', textDomain) }}</span>
            <textarea v-model="form.options" :class="inputClass" rows="5" />
          </label>

          <label class="flex flex-col gap-1">
            <span class="text-[12px] font-medium text-slate-500">{{ __('Position', textDomain) }}</span>
            <input v-model="form.position" :class="inputClass" max="10000" min="0" type="number" />
          </label>

          <p v-if="error" class="rounded-[8px] border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger">{{ error }}</p>

          <div class="flex justify-end gap-3">
            <BaseButton :disabled="saving" :title="__('Cancel', textDomain)" variant="secondary" @click="$emit('close')" />
            <BaseButton :loading="saving" :title="isEdit ? __('Save changes', textDomain) : __('Create field', textDomain)" type="submit" />
          </div>
        </form>
      </ModalDialog>
    </div>
  </Teleport>
</template>
