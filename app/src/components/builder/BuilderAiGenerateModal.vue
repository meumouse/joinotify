<script setup>
/**
 * BuilderAiGenerateModal.vue
 *
 * Modal for creating a workflow from a natural-language description. It collects
 * the user's instructions (plus optional advanced context) and emits a generate
 * event so the AI can build the trigger and steps for review.
 *
 * @since 2.0.0
 */
import { ref, watch } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import ModalDialog from '../modals/ModalDialog.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseTextarea from '../base/BaseTextarea.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'generate']);

const instructions = ref('');
const system = ref('');

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      instructions.value = '';
      system.value = '';
    }
  },
);

/**
 * Validate the instructions and emit the generate event with the trimmed
 * instructions and optional system context. No-ops while loading or empty.
 *
 * @since 2.0.0
 * @returns {void}
 */
function submit() {
  const value = instructions.value.trim();

  if (!value || props.loading) {
    return;
  }

  emit('generate', {
    instructions: value,
    system: system.value.trim(),
  });
}
</script>

<template>
  <ModalDialog
    :open="open"
    :title="__('Create workflow with AI', textDomain)"
    :description="__('Describe the automation in plain language. The AI builds the trigger and steps; you review and adjust before saving.', textDomain)"
    sizeClass="max-w-2xl"
    @close="$emit('close')"
  >
    <div class="space-y-5">
      <BaseTextarea
        v-model="instructions"
        :label="__('What should this workflow do?', textDomain)"
        :rows="6"
        :disabled="loading"
        :placeholder="__('e.g. When a WooCommerce order is completed, wait 1 hour, then send the customer a WhatsApp thank-you message with the order number.', textDomain)"
      />

      <details class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
        <summary class="cursor-pointer list-none text-sm font-semibold text-slate-700">
          {{ __('Advanced: extra instructions / documentation', textDomain) }}
        </summary>
        <div class="pt-3">
          <BaseTextarea
            v-model="system"
            :rows="4"
            :disabled="loading"
            :placeholder="__('Optional rules or context for the AI (brand voice, constraints, product details).', textDomain)"
          />
        </div>
      </details>

      <p v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        {{ error }}
      </p>

      <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
        <BaseButton
          :title="__('Cancel', textDomain)"
          variant="ghost"
          :disabled="loading"
          @click="$emit('close')"
        />
        <BaseButton
          :title="__('Generate workflow', textDomain)"
          :loading="loading"
          :disabled="!instructions.trim()"
          @click="submit"
        />
      </div>
    </div>
  </ModalDialog>
</template>
