<script setup lang="ts">
/**
 * DynamicPlaceholderSettings.vue
 *
 * Settings panel for the "Dynamic placeholder" action, which asks the AI to
 * generate a value and stores it under a named variable reusable later via an
 * {{ ai:name }} token. Configures the variable name, prompt, persona and an
 * optional per-node model override.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextareaField from '../../components/base/BaseTextareaField.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseNumberField from '../../components/base/BaseNumberField.vue';
import BaseAccordion from '../../components/base/BaseAccordion.vue';
import BaseRichTextArea from '../../../components/base/BaseRichTextArea.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useAiProviders } from '../../../composables/useAiProviders';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const usageToken = computed(() => {
  const name = String((props.modelValue as Record<string, unknown>).var_name || '').trim();
  return name ? `{{ ai:${name} }}` : '{{ ai:NAME }}';
});

const { providerSelectOptions, hasMultipleProviders, resolveProviderId, modelOptions } = useAiProviders();

// The provider a node effectively targets (falls back to the default provider).
const activeProvider = computed(() => resolveProviderId((props.modelValue as Record<string, unknown>).ai_provider));

// Models offered reflect the selected provider so the override stays valid.
const providerModelOptions = computed(() => modelOptions(activeProvider.value));

/**
 * Update a single key on the action model and emit the merged result.
 *
 * @since 2.0.0
 * @param {string} key Model key to update.
 * @param {unknown} value New value for the key.
 */
function update(key: string, value: unknown) {
  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    [key]: value,
  });
}

/**
 * Switch the routing provider and clear the model override, since a model
 * belongs to a single provider and would not exist on the new one.
 *
 * @param {string} value The newly selected provider id.
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
    <FieldGroup
      :title="__('Variable name', textDomain)"
      :description="__('Lowercase letters, numbers, and underscores. Used as the token below.', textDomain)"
    >
      <BaseTextField
        :model-value="String(modelValue.var_name || '')"
        :label="__('Variable name', textDomain)"
        :placeholder="__('e.g. greeting', textDomain)"
        @update:model-value="update('var_name', $event)"
      />
    </FieldGroup>

    <p class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
      {{ __('Reuse the generated value in any later message with:', textDomain) }}
      <code class="font-mono font-semibold">{{ usageToken }}</code>
    </p>

    <FieldGroup
      :title="__('Prompt / instructions', textDomain)"
      :description="__('Describe the value to generate. Use {{ placeholders }} to inject the trigger context.', textDomain)"
    >
      <BaseRichTextArea
        :model-value="String(modelValue.ai_prompt || '')"
        :label="__('Prompt', textDomain)"
        :rows="5"
        :placeholder="__('e.g. Write a one-line greeting for {{ wc_billing_first_name }}.', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('ai_prompt', $event)"
      />
    </FieldGroup>

    <FieldGroup
      :title="__('Persona / system message', textDomain)"
      :description="__('Optional rules, tone, and context for the AI.', textDomain)"
    >
      <BaseTextareaField
        :model-value="String(modelValue.ai_system || '')"
        :label="__('System message', textDomain)"
        :rows="3"
        :placeholder="__('You are the support agent for Store X. Be cordial and concise.', textDomain)"
        @update:model-value="update('ai_system', $event)"
      />
    </FieldGroup>

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

        <FieldGroup :title="__('Model', textDomain)" :description="__('Override the default model for this node only.', textDomain)">
          <BaseSelectField
            :model-value="String(modelValue.ai_model || '')"
            :options="providerModelOptions"
            :label="__('Model', textDomain)"
            @update:model-value="update('ai_model', $event)"
          />
        </FieldGroup>

        <FieldGroup :title="__('Temperature', textDomain)" :description="__('Creativity from 0 to 2. Leave empty to use the default.', textDomain)">
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
