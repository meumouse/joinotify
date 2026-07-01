<script setup lang="ts">
/**
 * SnippetPhpSettings.vue
 *
 * Settings panel for the "PHP snippet" action. Lets an administrator edit the
 * PHP code executed by the workflow runtime and optionally generate it from a
 * natural-language prompt via the AI helper on the store.
 *
 * @since 2.0.0
 */
import { ref } from 'vue';
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseCodeEditorField from '../../components/base/BaseCodeEditorField.vue';
import BaseTextareaField from '../../components/base/BaseTextareaField.vue';
import BaseButton from '../../../components/base/BaseButton.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const store = useWorkflowBuilderStore();

const aiPrompt = ref('');
const aiLoading = ref(false);
const aiError = ref('');

/**
 * Ask the AI helper to generate a PHP snippet from the entered instructions and
 * write the result into the action model.
 *
 * @since 2.0.0
 * @returns {Promise<void>}
 */
async function generateWithAi() {
  const instructions = aiPrompt.value.trim();

  if (!instructions || aiLoading.value) {
    return;
  }

  aiLoading.value = true;
  aiError.value = '';

  try {
    const result = await store.generateAiSnippet({ instructions });

    if (!result || result.ok === false) {
      aiError.value = result?.message || __('The AI could not generate the snippet.', textDomain);
      return;
    }

    emit('update:modelValue', { ...props.modelValue, snippet_php: result.code });
  } finally {
    aiLoading.value = false;
  }
}
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      tone="danger"
      :title="__('Security warning', textDomain)"
      :message="__('PHP snippets execute inside the workflow runtime. Only trusted administrators should edit this code.', textDomain)"
    />

    <details class="rounded-lg border border-indigo-200 bg-indigo-50/60 px-4 py-3">
      <summary class="cursor-pointer list-none text-sm font-semibold text-indigo-800">
        {{ __('Generate with AI', textDomain) }}
      </summary>
      <div class="space-y-3 pt-3">
        <BaseTextareaField
          :model-value="aiPrompt"
          :rows="3"
          :disabled="aiLoading"
          :placeholder="__('Describe what the snippet should do. The runtime exposes $payload with the trigger context.', textDomain)"
          @update:model-value="aiPrompt = $event"
        />

        <p v-if="aiError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
          {{ aiError }}
        </p>

        <div class="flex justify-end">
          <BaseButton
            :title="__('Generate snippet', textDomain)"
            :loading="aiLoading"
            :disabled="!aiPrompt.trim()"
            @click="generateWithAi"
          />
        </div>
      </div>
    </details>

    <FieldGroup :title="__('PHP snippet', textDomain)" :description="__('The field is required. Keep the code self-contained and deterministic.', textDomain)">
      <BaseCodeEditorField
        :model-value="String(modelValue.snippet_php || '')"
        :label="__('PHP Snippet', textDomain)"
        placeholder="<?php"
        :rows="14"
        @update:model-value="$emit('update:modelValue', { ...modelValue, snippet_php: $event })"
      />
    </FieldGroup>

    <PlaceholderList
      v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      @select="$emit('placeholder-selected', $event)"
    />
  </div>
</template>
