<script setup>

/**
 * IntegrationSettingsModal.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import FieldRow from '../../../../components/fields/FieldRow.vue';

const modalSizeClasses = {
  small: 'max-w-[640px]',
  medium: 'max-w-[900px]',
  large: 'max-w-[1200px]',
  'extra-large': 'max-w-[1400px]',
};

const props = defineProps({
  open: { type: Boolean, default: false },
  integration: { type: Object, default: null },
  settings: { type: Object, default: () => ({}) },
  modalSize: { type: String, default: 'medium' },
});

defineEmits(['close', 'update-setting']);

const modal = computed(() => props.integration?.modal || {});
const fields = computed(() => props.integration?.settings || props.integration?.fields || []);
const resolvedSizeClass = computed(() => {
  const value = String(props.modalSize || 'medium').trim().toLowerCase();

  if (modalSizeClasses[value]) {
    return modalSizeClasses[value];
  }

  if (value.startsWith('max-w-')) {
    return value;
  }

  return modalSizeClasses.medium;
});
</script>

<template>
  <ModalDialog
    :open="open"
    :title="modal.title || integration?.title || __('Integration settings', textDomain)"
    :description="modal.description || integration?.description || ''"
    :eyebrow="__('Integrations', textDomain)"
    :size-class="resolvedSizeClass"
    @close="$emit('close')"
  >
    <div v-if="fields.length" class="space-y-4">
      <FieldRow
        v-for="field in fields"
        :key="field.key"
        :field="field"
        :name="field.key"
        :model-value="settings[field.key]"
        @update:model-value="$emit('update-setting', field.key, $event)"
      />
    </div>
    <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
      {{ __('This integration has no additional settings.', textDomain) }}
    </div>
  </ModalDialog>
</template>
