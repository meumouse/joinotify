<script setup lang="ts">
/**
 * TemplateVariablesForm.vue
 *
 * One field per variable slot of a WhatsApp message template: the text of the
 * header and body tokens, the file of a media header (with the WordPress media
 * library), the coupon of a copy-code button, the end of a URL button and the
 * other token-less slots described in utils/whatsappTemplates.
 *
 * The parent owns the variable map; every change emits the whole map back.
 * Without `placeholders` the fields take plain values only and no variable
 * picker is shown.
 *
 * @since 2.8.0
 */
import { ImagePlus } from '@boxicons/vue';
import BaseTextFieldVariables from '../../builder/components/base/BaseTextFieldVariables.vue';
import { __, textDomain } from '../../utils/i18n';
import { cardOf, isMediaHeader, variableLabel, variablePlaceholder, type TemplateVariable } from '../../utils/whatsappTemplates';
import type { WorkflowPlaceholderItem } from '../../types/workflowBuilder';

const props = defineProps({
  variables: { type: Array as () => TemplateVariable[], default: () => [] },
  placeholders: { type: Array as () => Array<WorkflowPlaceholderItem | string>, default: () => [] },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits<{ (event: 'update:variables', value: TemplateVariable[]): void }>();

/**
 * Store the value of one variable slot.
 *
 * @since 2.3.0
 * @version 2.8.0
 * @param {number} position Index of the slot in the map.
 * @param {string} value Placeholder or literal filling the slot.
 * @returns {void}
 */
function updateVariable(position: number, value: string): void {
  const variables = Array.isArray(props.variables) ? [...props.variables] : [];

  if (!variables[position]) {
    return;
  }

  variables[position] = { ...variables[position], value };
  emit('update:variables', variables);
}

/**
 * Open the WordPress media library and store the chosen file's URL on a
 * media header slot.
 *
 * @since 2.6.0
 * @version 2.8.0
 * @param {number} position Index of the slot in the map.
 * @param {string} format Media kind the template header accepts.
 * @returns {void}
 */
function openMediaLibrary(position: number, format: string): void {
  const wpMedia = (window as Window & {
    wp?: {
      media?: (...args: unknown[]) => {
        on: (event: string, callback: () => void) => void;
        state: () => { get: (key: string) => { first: () => { toJSON: () => { url?: string } } | undefined } };
        open: () => void;
      };
    };
  }).wp?.media;

  if (!wpMedia) {
    return;
  }

  // The library has no "document" type; any file stays selectable for it.
  const library = format === 'image' || format === 'video' ? { type: format } : {};
  const frame = wpMedia({ title: __('Select media', textDomain), button: { text: __('Use this media', textDomain) }, library, multiple: false });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first();
    const url = String(attachment?.toJSON?.().url || '').trim();

    if (url) {
      updateVariable(position, url);
    }
  });

  frame.open();
}

const hasMediaLibrary = () => typeof (window as Window & { wp?: { media?: unknown } }).wp?.media === 'function';
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="(variable, position) in variables"
      :key="`${cardOf(variable)}-${variable.component}-${variable.index}-${variable.sub_type}-${variable.format}-${variable.key}`"
      class="flex items-end gap-2"
    >
      <div class="flex-1">
        <BaseTextFieldVariables
          :model-value="String(variable.value || '')"
          :label="variableLabel(variable)"
          :placeholder="variablePlaceholder(variable, placeholders.length > 0)"
          :placeholders="placeholders"
          :disabled="disabled"
          @update:model-value="updateVariable(position, $event)"
        />
      </div>
      <button
        v-if="isMediaHeader(variable) && hasMediaLibrary()"
        type="button"
        class="mb-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="disabled"
        @click="openMediaLibrary(position, String(variable.format))"
      >
        <ImagePlus class="text-base" />
        {{ __('Library', textDomain) }}
      </button>
    </div>
  </div>
</template>
