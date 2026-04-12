<script setup>
import { __, textDomain } from '../../../utils/i18n';
import BaseButton from '../../../components/buttons/BaseButton.vue';

defineProps({
  hasUnsavedChanges: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
});

defineEmits(['save']);

const saveButtonIcon =
  '<svg class="w-[1.225rem] h-[1.225rem]" xmlns="http://www.w3.org/2000/svg" style="fill: #fff;" viewBox="0 0 24 24"><path d="M5 21h14a2 2 0 0 0 2-2V8a1 1 0 0 0-.29-.71l-4-4A1 1 0 0 0 16 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2zm10-2H9v-5h6zM13 7h-2V5h2zM5 5h2v4h8V5h.59L19 8.41V19h-2v-5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v5H5z"></path></svg>';
</script>

<template>
  <div class="sticky bottom-0 inset-x-0 z-[1059] border-t border-black/10 bg-white/80 px-12 py-8 backdrop-blur-[5px]">
    <BaseButton
      :title="__('Save changes', textDomain)"
      :icon="saveButtonIcon"
      color="primary"
      size="lg"
      :loading="saving"
      :disabled="!hasUnsavedChanges"
      @click="$emit('save')"
    />
  </div>
</template>
