<script setup>

/**
 * GeneralSettingsSection.vue frontend component.
 *
 * Renders the general fields, and the switches that carry a modal of their
 * own options (the Joinotify Cloud sync): the switch, what switching it on
 * sends, and a "Configure" button that opens the modal once it is on.
 *
 * @since 1.4.7
 * @version 2.5.0
 */
import { __, textDomain } from '../../../../utils/i18n';
import BaseButton from '../../../../components/base/BaseButton.vue';
import FieldControl from '../../../../components/fields/FieldControl.vue';
import FieldRow from '../../../../components/fields/FieldRow.vue';

defineProps({
  generalVisibleFields: { type: Array, default: () => [] },
  generalModalFields: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['update-setting', 'configure']);
</script>

<template>
  <div class="space-y-2">
    <FieldRow
      v-for="field in generalVisibleFields"
      :key="field.key"
      :field="field"
      :name="field.key"
      :model-value="settings[field.key]"
      @update:model-value="$emit('update-setting', field.key, $event)"
    />

    <div
      v-for="field in generalModalFields"
      :key="field.key"
      class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center"
    >
      <div>
        <h3 class="text-[15px] font-semibold text-slate-800">{{ field.label }}</h3>
        <p v-if="field.description" class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">{{ field.description }}</p>
        <details v-if="field.disclosure" class="mt-2 max-w-xl text-[13px] leading-5 text-slate-500">
          <summary class="cursor-pointer font-medium text-primary-700">{{ __('What is sent', textDomain) }}</summary>
          <p class="mt-2">{{ field.disclosure }}</p>
        </details>
      </div>

      <div class="field-control flex items-center gap-4">
        <FieldControl
          :field="field"
          :name="field.key"
          :model-value="settings[field.key]"
          @update:model-value="$emit('update-setting', field.key, $event)"
        />
        <BaseButton
          :disabled="settings[field.key] !== 'yes'"
          size="sm"
          :title="field.modal?.button_label || __('Configure', textDomain)"
          variant="secondary"
          @click="$emit('configure', field)"
        />
      </div>
    </div>
  </div>
</template>
