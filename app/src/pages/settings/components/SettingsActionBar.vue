<script setup>

/**
 * SettingsActionBar.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../utils/i18n';
import BaseButton from '../../../components/buttons/BaseButton.vue';

defineProps({
  hasUnsavedChanges: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
});

defineEmits(['save']);
</script>

<template>
  <div class="sticky bottom-0 inset-x-0 z-[1059] border-t border-black/10 bg-white/80 px-12 py-6 backdrop-blur-[5px]">
    <BaseButton
      :title="__('Save changes', textDomain)"
      icon-name="save"
      icon-class="w-[1.225rem] h-[1.225rem] text-white"
      color="primary"
      size="lg"
      :loading="saving"
      :disabled="!hasUnsavedChanges"
      @click="$emit('save')"
    />
  </div>
</template>
