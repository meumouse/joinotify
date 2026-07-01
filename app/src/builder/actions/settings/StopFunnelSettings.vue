<script setup lang="ts">
/**
 * StopFunnelSettings.vue
 *
 * Settings panel for the "Stop funnel" action. The action halts the workflow
 * immediately and exposes no editable settings, so this panel only renders an
 * informational notice.
 *
 * @since 2.0.0
 */
import BaseAlert from '../../components/base/BaseAlert.vue';
import { __, textDomain } from '../../../utils/i18n';

defineProps({
  modelValue: { type: Object, default: () => ({}) },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      tone="neutral"
      :title="__('No configuration required', textDomain)"
      :message="__('This action stops the workflow immediately. There are no editable settings.', textDomain)"
    />
  </div>
</template>
