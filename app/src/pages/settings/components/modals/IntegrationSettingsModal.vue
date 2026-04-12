<script setup>

/**
 * IntegrationSettingsModal.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';
import FieldRow from '../../../../components/fields/FieldRow.vue';

defineProps({
  open: { type: Boolean, default: false },
  integration: { type: Object, default: null },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['close', 'update-setting']);
</script>

<template>
  <ModalDialog
    :open="open"
    :title="integration?.title || __('Integration settings', textDomain)"
    :description="integration?.description || ''"
    :eyebrow="__('Integrations', textDomain)"
    @close="$emit('close')"
  >
    <div v-if="integration?.fields?.length" class="space-y-4">
      <FieldRow
        v-for="field in integration.fields"
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
