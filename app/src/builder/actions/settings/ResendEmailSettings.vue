<script setup lang="ts">
/**
 * ResendEmailSettings.vue
 *
 * Settings panel for the "Send e-mail (Resend)" action. Configures the
 * recipient e-mail, subject and body (all with placeholder support) delivered
 * through the Resend API configured in the integration settings.
 *
 * @since 2.1.0
 */
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import BaseRichTextArea from '../../../components/base/BaseRichTextArea.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const { update } = useActionSettingsUpdate(props, emit);
</script>

<template>
  <div class="space-y-4">
    <FieldGroup :title="__('Recipient', textDomain)" :description="__('E-mail address or a placeholder that resolves to one.', textDomain)">
      <BaseTextFieldVariables
        :model-value="String(modelValue.receiver || '')"
        :label="__('Recipient', textDomain)"
        :placeholder="__('{{ wc_billing_email }} or customer@example.com', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('receiver', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Subject', textDomain)">
      <BaseTextFieldVariables
        :model-value="String(modelValue.subject || '')"
        :label="__('Subject', textDomain)"
        :placeholder="__('Your order is confirmed', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('subject', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Message', textDomain)">
      <BaseRichTextArea
        :model-value="String(modelValue.message || '')"
        :label="__('Message', textDomain)"
        :rows="6"
        :placeholder="__('Type your e-mail... Use {{ placeholders }} and *bold*', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('message', $event)"
      />
    </FieldGroup>
  </div>
</template>
