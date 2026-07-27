<script setup lang="ts">
/**
 * WhatsappAiMessageSettings.vue
 *
 * Settings panel for the "WhatsApp AI message" action. Configures the sender,
 * recipient, prompt, persona, tone/length and an optional per-node model
 * override used to generate and send an AI-written WhatsApp message.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import BaseTextareaField from '../../components/base/BaseTextareaField.vue';
import BaseNumberField from '../../components/base/BaseNumberField.vue';
import BaseAccordion from '../../components/base/BaseAccordion.vue';
import BaseRichTextArea from '../../../components/base/BaseRichTextArea.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useSenderOptions } from '../../../composables/useSenderOptions';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { useAiProviders } from '../../../composables/useAiProviders';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const senderOptions = useSenderOptions(() => (props.modelValue as Record<string, unknown>).sender);

const toneOptions = computed(() => [
  { label: __('Friendly', textDomain), value: 'friendly' },
  { label: __('Casual', textDomain), value: 'casual' },
  { label: __('Formal', textDomain), value: 'formal' },
]);

const lengthOptions = computed(() => [
  { label: __('Short', textDomain), value: 'short' },
  { label: __('Medium', textDomain), value: 'medium' },
  { label: __('Long', textDomain), value: 'long' },
]);

const { providerSelectOptions, hasMultipleProviders, resolveProviderId, modelOptions } = useAiProviders();

// The provider a node effectively targets (falls back to the default provider).
const activeProvider = computed(() => resolveProviderId((props.modelValue as Record<string, unknown>).ai_provider));

// Models offered reflect the selected provider so the override stays valid.
const providerModelOptions = computed(() => modelOptions(activeProvider.value));

const { update } = useActionSettingsUpdate(props, emit);

/**
 * Switch the routing provider and clear the model override, since a model
 * belongs to a single provider and would not exist on the new one.
 *
 * @param value The newly selected provider id.
 */
function onProviderChange(value: string) {
  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    ai_provider: value,
    ai_model: '',
  });
}
</script>

<template>
  <div class="space-y-4">
    <FieldGroup :title="__('Sender', textDomain)" :description="__('WhatsApp number that will send the message.', textDomain)">
      <BaseSelectField
        :model-value="String(modelValue.sender || '')"
        :options="senderOptions"
        :label="__('Sender', textDomain)"
        @update:model-value="update('sender', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Recipient', textDomain)" :description="__('Phone number or a placeholder that resolves to one.', textDomain)">
      <BaseTextFieldVariables
        :model-value="String(modelValue.receiver || '')"
        :label="__('Recipient', textDomain)"
        :placeholder="__('{{ wc_billing_phone }} or +5511999990000', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('receiver', $event)"
      />
    </FieldGroup>

    <FieldGroup
      :title="__('Prompt / instructions', textDomain)"
      :description="__('Describe what the AI should write. Use {{ placeholders }} to inject the trigger context.', textDomain)"
    >
      <BaseRichTextArea
        :model-value="String(modelValue.ai_prompt || '')"
        :label="__('Prompt', textDomain)"
        :rows="6"
        :placeholder="__('e.g. Thank {{ wc_billing_first_name }} for order #{{ order_id }} and confirm it is being prepared.', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('ai_prompt', $event)"
      />
    </FieldGroup>

    <FieldGroup
      :title="__('Persona / system message', textDomain)"
      :description="__('Tone, personality, and brand rules for the AI. Optional — it adds to the global persona from settings.', textDomain)"
    >
      <BaseTextareaField
        :model-value="String(modelValue.ai_system || '')"
        :label="__('System message', textDomain)"
        :rows="4"
        :placeholder="__('You are the support agent for Store X. Be cordial and concise.', textDomain)"
        @update:model-value="update('ai_system', $event)"
      />
    </FieldGroup>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <FieldGroup :title="__('Tone', textDomain)">
        <BaseSelectField
          :model-value="String(modelValue.ai_tone || 'friendly')"
          :options="toneOptions"
          :label="__('Tone', textDomain)"
          @update:model-value="update('ai_tone', $event)"
        />
      </FieldGroup>

      <FieldGroup :title="__('Length', textDomain)">
        <BaseSelectField
          :model-value="String(modelValue.ai_length || 'medium')"
          :options="lengthOptions"
          :label="__('Length', textDomain)"
          @update:model-value="update('ai_length', $event)"
        />
      </FieldGroup>
    </div>

    <BaseAccordion :title="__('Advanced (model override)', textDomain)" :open="false">
      <div class="space-y-4">
        <FieldGroup
          v-if="hasMultipleProviders"
          :title="__('AI provider', textDomain)"
          :description="__('Route this node to a specific engine. Leave on the default to use the provider from settings.', textDomain)"
        >
          <BaseSelectField
            :model-value="String(modelValue.ai_provider || '')"
            :options="providerSelectOptions"
            :label="__('AI provider', textDomain)"
            @update:model-value="onProviderChange($event)"
          />
        </FieldGroup>

        <FieldGroup
          :title="__('Model', textDomain)"
          :description="__('Override the default model for this node only.', textDomain)"
        >
          <BaseSelectField
            :model-value="String(modelValue.ai_model || '')"
            :options="providerModelOptions"
            :label="__('Model', textDomain)"
            @update:model-value="update('ai_model', $event)"
          />
        </FieldGroup>

        <FieldGroup
          :title="__('Temperature', textDomain)"
          :description="__('Creativity from 0 (deterministic) to 2 (more creative). Leave empty to use the default.', textDomain)"
        >
          <BaseNumberField
            :model-value="String(modelValue.ai_temperature ?? '')"
            :label="__('Temperature', textDomain)"
            :min="0"
            :max="2"
            :step="0.1"
            :placeholder="__('Default', textDomain)"
            @update:model-value="update('ai_temperature', $event)"
          />
        </FieldGroup>
      </div>
    </BaseAccordion>
  </div>
</template>
