<script setup lang="ts">
/**
 * ConnectionNotice.vue
 *
 * Tells the user, on a WhatsApp step, that this site is not connected to
 * Joinotify yet, so the step has nothing to send through.
 *
 * WhatsApp steps are always offered since 2.8.0 (the integration has no
 * on/off switch any more), so this notice is what replaces the empty library a
 * site without a connection used to see. Renders nothing on the legacy relay,
 * whose connection lives elsewhere.
 *
 * @since 2.8.0
 */
import { computed } from 'vue';
import BaseAlert from './BaseAlert.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  action: { type: String, default: '' },
});

const store = useWorkflowBuilderStore();

const transport = computed(() => (store.bootstrap?.transport || {}) as Record<string, unknown>);

/**
 * Whether the selected step sends a WhatsApp message.
 *
 * The free-form actions plus the template action: every step the Cloud API
 * transport delivers.
 *
 * @since 2.8.0
 * @returns {boolean} True for a WhatsApp step.
 */
const isWhatsappStep = computed(() => {
  const freeForm = Array.isArray(transport.value.free_form_actions) ? (transport.value.free_form_actions as string[]) : [];
  const template = String(transport.value.template_action || 'send_whatsapp_message_template');

  return props.action === template || freeForm.includes(props.action);
});

const visible = computed(
  () => Boolean(transport.value.is_cloud) && transport.value.cloud_ready === false && isWhatsappStep.value
);

const settingsUrl = computed(() => String(transport.value.settings_url || ''));
</script>

<template>
  <BaseAlert
    v-if="visible"
    tone="warning"
    :title="__('This site is not connected to Joinotify', textDomain)"
  >
    <p class="mt-1">
      {{ __('WhatsApp messages are sent through your Joinotify account. Until this site is connected, this step cannot send anything.', textDomain) }}
    </p>

    <p v-if="settingsUrl" class="mt-2">
      <a class="font-semibold underline underline-offset-2" :href="settingsUrl" target="_blank" rel="noopener noreferrer">
        {{ __('Connect in Settings → General', textDomain) }}
      </a>
    </p>
  </BaseAlert>
</template>
