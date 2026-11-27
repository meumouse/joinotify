<script setup lang="ts">
/**
 * TemplatePreview.vue
 *
 * Preview of a WhatsApp message template: the header (or the kind of media it
 * carries), the body, the footer, the buttons and, for a carousel, its cards.
 *
 * With `variables`, the header and body tokens show the values typed so far;
 * a token still empty stays as written. Without them the template is shown
 * as approved, tokens included.
 *
 * @since 2.8.0
 */
import { computed } from 'vue';
import { ImagePlus } from '@boxicons/vue';
import { __, textDomain } from '../../utils/i18n';
import { fillTemplateText, type RemoteTemplate, type TemplateVariable } from '../../utils/whatsappTemplates';

const props = defineProps({
  template: { type: Object as () => RemoteTemplate, required: true },
  variables: { type: Array as () => TemplateVariable[] | null, default: null },
});

const header = computed(() =>
  props.variables ? fillTemplateText(props.template.header, 'header', props.variables) : props.template.header
);

const body = computed(() =>
  props.variables ? fillTemplateText(props.template.body, 'body', props.variables) : props.template.body
);
</script>

<template>
  <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-4 py-3">
    <p
      v-if="template.header_format && template.header_format !== 'TEXT'"
      class="mb-2 inline-flex items-center gap-1 rounded-[6px] bg-slate-200 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-600"
    >
      <ImagePlus class="text-sm" />
      {{ String(template.header_format).toLowerCase() }}
    </p>
    <p v-if="header" class="text-[13px] font-semibold text-slate-700">{{ header }}</p>
    <p class="whitespace-pre-line text-[13px] leading-5 text-slate-600">{{ body }}</p>
    <p v-if="template.footer" class="mt-1 text-xs text-slate-400">{{ template.footer }}</p>
    <div v-if="template.buttons?.length" class="mt-2 flex flex-wrap gap-1.5">
      <span
        v-for="(button, buttonIndex) in template.buttons"
        :key="`${buttonIndex}-${button.type}`"
        class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600"
      >
        {{ button.text || button.type }}
      </span>
    </div>
    <div v-if="template.cards?.length" class="mt-3 flex gap-2 overflow-x-auto pb-1">
      <div
        v-for="(card, cardIndex) in template.cards"
        :key="`card-${cardIndex}`"
        class="w-40 shrink-0 rounded-[8px] border border-slate-200 bg-white px-3 py-2"
      >
        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
          {{ __('Card', textDomain) }} {{ cardIndex + 1 }} · {{ String(card.header_format || '').toLowerCase() }}
        </p>
        <p class="mt-1 whitespace-pre-line text-xs leading-4 text-slate-600">{{ card.body }}</p>
        <p v-if="card.buttons?.length" class="mt-1 text-[11px] text-slate-400">
          {{ card.buttons.map((button) => button.text || button.type).join(' · ') }}
        </p>
      </div>
    </div>
  </div>
</template>
