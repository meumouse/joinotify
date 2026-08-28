<script setup>

/**
 * GeneralSettingsSection.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../../utils/i18n';
import FieldRow from '../../../../components/fields/FieldRow.vue';

defineProps({
  generalVisibleFields: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['update-setting']);
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
  </div>
</template>
