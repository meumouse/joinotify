<script setup>

/**
 * GeneralSettingsSection.vue frontend component.
 *
 * Opens with the Joinotify connection card, then renders the general fields,
 * and the switches that carry a modal of their own options (the Joinotify
 * Cloud sync): the switch, what switching it on sends, and a "Configure"
 * button that opens the modal once it is on.
 *
 * @since 1.4.7
 * @version 2.8.0
 */
import { __, textDomain } from '../../../../utils/i18n';
import BaseButton from '../../../../components/base/BaseButton.vue';
import FieldControl from '../../../../components/fields/FieldControl.vue';
import FieldRow from '../../../../components/fields/FieldRow.vue';
import JoinotifyConnectionCard from '../cards/JoinotifyConnectionCard.vue';

defineProps({
  generalVisibleFields: { type: Array, default: () => [] },
  generalModalFields: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
  connection: { type: Object, default: () => ({}) },
  phones: { type: Object, default: () => ({ senders: [], sender_count: 0 }) },
  syncing: { type: Boolean, default: false },
});

defineEmits(['update-setting', 'configure', 'update:connection', 'update:phones', 'sync', 'manage-numbers', 'notify']);
</script>

<template>
  <div class="space-y-2">
    <JoinotifyConnectionCard
      class="mb-6"
      :connection="connection"
      :phones="phones"
      :settings="settings"
      :syncing="syncing"
      @update:connection="$emit('update:connection', $event)"
      @update:phones="$emit('update:phones', $event)"
      @update-setting="(key, value) => $emit('update-setting', key, value)"
      @sync="$emit('sync')"
      @manage-numbers="$emit('manage-numbers')"
      @notify="(message, tone) => $emit('notify', message, tone)"
    />

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
