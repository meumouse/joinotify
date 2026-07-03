<script setup lang="ts">
/**
 * TelegramTextSettings.vue
 *
 * Settings panel for the "Telegram: message" action. Configures the destination
 * chat id (with placeholder support) and the message body sent through the
 * Telegram bot configured in the integration settings.
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
    <FieldGroup :title="__('Chat id', textDomain)" :description="__('Telegram chat, group or channel id. Accepts a placeholder that resolves to one.', textDomain)">
      <BaseTextFieldVariables
        :model-value="String(modelValue.receiver || '')"
        :label="__('Chat id', textDomain)"
        :placeholder="__('-1001234567890 or {{ telegram_chat_id }}', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('receiver', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Message', textDomain)">
      <BaseRichTextArea
        :model-value="String(modelValue.message || '')"
        :label="__('Message', textDomain)"
        :rows="6"
        :placeholder="__('Type your message... Use {{ placeholders }} and *bold*', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('message', $event)"
      />
    </FieldGroup>
  </div>
</template>
