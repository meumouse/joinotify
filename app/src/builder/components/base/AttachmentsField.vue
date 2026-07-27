<script setup lang="ts">
/**
 * AttachmentsField.vue
 *
 * Reusable attachment list for actions that can deliver files. Each item declares
 * where the file comes from: a WordPress media library pick, an arbitrary URL that
 * may contain placeholders, or every downloadable file granted to the order.
 *
 * The component only stores the declaration. Turning it into real files happens at
 * runtime in the PHP resolver, so an order-based item stays valid across orders.
 *
 * @since 2.1.0
 */
import { computed } from 'vue';
import BaseTextFieldVariables from './BaseTextFieldVariables.vue';
import BaseSelectField from './BaseSelectField.vue';
import { ImagePlus, Trash, Plus } from '@boxicons/vue';
import { __, textDomain } from '../../../utils/i18n';

type AttachmentSource = 'media' | 'url' | 'order_downloads';

interface AttachmentItem {
  source: AttachmentSource;
  name?: string;
  url?: string;
  attachment_id?: number;
}

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  availablePlaceholders: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const sourceOptions = [
  { label: __('Media library file', textDomain), value: 'media' },
  { label: __('File URL', textDomain), value: 'url' },
  { label: __('Order digital files', textDomain), value: 'order_downloads' },
];

const items = computed<AttachmentItem[]>(() => (Array.isArray(props.modelValue) ? (props.modelValue as AttachmentItem[]) : []));

/**
 * Emit a new list with one item replaced.
 *
 * @since 2.1.0
 */
function patchItem(index: number, patch: Partial<AttachmentItem>) {
  const next = items.value.map((item, position) => (position === index ? { ...item, ...patch } : item));

  emit('update:modelValue', next);
}

/**
 * Append an empty URL item, which is the source that needs no extra lookup.
 *
 * @since 2.1.0
 */
function addItem() {
  emit('update:modelValue', [...items.value, { source: 'url', url: '', name: '' }]);
}

function removeItem(index: number) {
  emit('update:modelValue', items.value.filter((_, position) => position !== index));
}

/**
 * Switch the source of an item, dropping the keys that no longer apply so a stale
 * attachment id or URL cannot leak into the saved payload.
 *
 * @since 2.1.0
 */
function changeSource(index: number, source: string) {
  const next: AttachmentItem = { source: source as AttachmentSource, name: items.value[index]?.name || '' };

  if (source === 'url') {
    next.url = items.value[index]?.url || '';
  }

  if (source === 'media') {
    next.attachment_id = items.value[index]?.attachment_id || 0;
    next.url = items.value[index]?.url || '';
  }

  // replaces the item instead of merging, so the dropped keys really go away
  emit('update:modelValue', items.value.map((item, position) => (position === index ? next : item)));
}

/**
 * Open the WordPress media library frame and store both the attachment id and its
 * URL. The id is what the resolver prefers, because it survives a URL change.
 *
 * @since 2.1.0
 */
function openMediaLibrary(index: number) {
  const wpMedia = (window as Window & {
    wp?: {
      media?: (...args: unknown[]) => {
        on: (event: string, callback: () => void) => void;
        state: () => { get: (key: string) => { first: () => { toJSON: () => { id?: number; url?: string; filename?: string } } | undefined } };
        open: () => void;
      };
    };
  }).wp?.media;

  if (!wpMedia) {
    return;
  }

  const frame = wpMedia({
    title: __('Select file', textDomain),
    button: { text: __('Use this file', textDomain) },
    multiple: false,
  });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first()?.toJSON?.();

    if (!attachment) {
      return;
    }

    patchItem(index, {
      attachment_id: Number(attachment.id) || 0,
      url: String(attachment.url || '').trim(),
      name: String(attachment.filename || '').trim(),
    });
  });

  frame.open();
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="(item, index) in items"
      :key="index"
      class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-3"
    >
      <div class="flex items-start gap-2">
        <div class="flex-1">
          <BaseSelectField
            :model-value="item.source || 'url'"
            :options="sourceOptions"
            :label="__('Source', textDomain)"
            :disabled="disabled"
            @update:model-value="changeSource(index, String($event))"
          />
        </div>

        <button
          type="button"
          class="mt-6 inline-flex shrink-0 items-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600"
          :disabled="disabled"
          :title="__('Remove attachment', textDomain)"
          @click="removeItem(index)"
        >
          <Trash class="text-base" />
        </button>
      </div>

      <p v-if="item.source === 'order_downloads'" class="text-sm leading-6 text-slate-500">
        {{ __('Attaches every digital file the customer purchased in this order. Resolved when the workflow runs, so it always matches the order that triggered it.', textDomain) }}
      </p>

      <div v-else-if="item.source === 'media'" class="flex items-end gap-2">
        <div class="flex-1">
          <BaseTextFieldVariables
            :model-value="String(item.url || '')"
            :label="__('File', textDomain)"
            :placeholder="__('Pick a file from the library', textDomain)"
            :placeholders="availablePlaceholders"
            :disabled="disabled"
            @update:model-value="patchItem(index, { url: String($event), attachment_id: 0 })"
          />
        </div>

        <button
          type="button"
          class="mb-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          :disabled="disabled"
          @click="openMediaLibrary(index)"
        >
          <ImagePlus class="text-base" />
          {{ __('Library', textDomain) }}
        </button>
      </div>

      <BaseTextFieldVariables
        v-else
        :model-value="String(item.url || '')"
        :label="__('File URL', textDomain)"
        placeholder="https://example.com/guide.pdf"
        :placeholders="availablePlaceholders"
        :disabled="disabled"
        @update:model-value="patchItem(index, { url: String($event) })"
      />
    </div>

    <button
      type="button"
      class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-400 hover:bg-slate-50"
      :disabled="disabled"
      @click="addItem"
    >
      <Plus class="text-base" />
      {{ __('Add attachment', textDomain) }}
    </button>
  </div>
</template>
