<script setup>

/**
 * AiStep.vue frontend component.
 *
 * Optional: picks an AI provider and takes its API key. Skipping leaves the
 * AI actions in the builder untouched — they simply stay unconfigured.
 *
 * @since 2.4.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import SelectField from '../../../../components/fields/SelectField.vue';

const props = defineProps({
  providers: { type: Array, default: () => [] },
  provider: { type: String, default: '' },
  apiKey: { type: String, default: '' },
  apiKeyStored: { type: Boolean, default: false },
});

defineEmits(['update:provider', 'update:apiKey']);

const providerField = computed(() => ({
  label: __('AI provider', textDomain),
  options: props.providers.map((entry) => ({ value: entry.id, label: entry.label })),
}));

const selected = computed(() => props.providers.find((entry) => entry.id === props.provider) || null);
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('Joinotify can use a language model to write message copy and suggest whole workflows. This is optional and you can set it up later — the plugin works fully without it.', textDomain) }}
    </p>

    <div class="max-w-md space-y-5">
      <div>
        <p class="mb-2 text-[14px] font-medium text-slate-700">
          {{ __('AI provider', textDomain) }}
        </p>

        <SelectField
          :field="providerField"
          name="ai_provider"
          :model-value="provider"
          @update:model-value="$emit('update:provider', $event)"
        />
      </div>

      <div v-if="selected">
        <label class="mb-2 block text-[14px] font-medium text-slate-700" for="joinotify-onboarding-ai-key">
          {{ __('Provider API key', textDomain) }}
        </label>

        <input
          id="joinotify-onboarding-ai-key"
          :value="apiKey"
          type="password"
          autocomplete="off"
          spellcheck="false"
          :placeholder="apiKeyStored ? __('A key is already saved. Type a new one to replace it.', textDomain) : selected.placeholder"
          class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
          @input="$emit('update:apiKey', $event.target.value)"
        >

        <p class="mt-2 text-[13px] leading-5 text-shell-500">
          {{ __('The key is stored on this site and used only for the AI actions you add to a workflow.', textDomain) }}
          <a
            v-if="selected.docs_url"
            class="font-semibold text-primary-700 underline underline-offset-4"
            :href="selected.docs_url"
            target="_blank"
            rel="noopener noreferrer"
          >{{ __('Where do I find it?', textDomain) }}</a>
        </p>
      </div>
    </div>
  </div>
</template>
