<script setup lang="ts">
import { computed } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextareaField from '../../components/base/BaseTextareaField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const store = useWorkflowBuilderStore();

const mediaTypeOptions = [
  { label: 'Image', value: 'image' },
  { label: 'Video', value: 'video' },
  { label: 'Document', value: 'document' },
  { label: 'Audio', value: 'audio' },
];

const senderOptions = computed(() => {
  const senders = Array.isArray(store.bootstrap?.phones?.senders) ? store.bootstrap.phones.senders : [];
  const options = senders
    .map((item: unknown) => String((item && typeof item === 'object' ? (item as Record<string, unknown>).phone : '') || '').trim())
    .filter(Boolean)
    .map((phone: string) => ({ label: phone, value: phone }));

  const current = String((props.modelValue as Record<string, unknown>).sender || '').trim();

  if (current && !options.some((option) => option.value === current)) {
    options.unshift({ label: current, value: current });
  }

  return [{ label: '— Select a sender —', value: '' }, ...options];
});

const isAudio = computed(() => String((props.modelValue as Record<string, unknown>).media_type || 'image') === 'audio');

function update(key: string, value: unknown) {
  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    [key]: value,
  });
}

function insertPlaceholder(placeholder: string) {
  const current = String((props.modelValue as Record<string, unknown>).caption || '');
  update('caption', current ? `${current} ${placeholder}` : placeholder);
  emit('placeholder-selected', placeholder);
}

function openMediaLibrary() {
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

  const frame = wpMedia({ title: 'Select media', button: { text: 'Use this media' }, multiple: false });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first();
    const url = String(attachment?.toJSON?.().url || '').trim();

    if (url) {
      update('media_url', url);
    }
  });

  frame.open();
}
</script>

<template>
  <div class="space-y-4">
    <FieldGroup title="Sender" description="WhatsApp number that will send the media.">
      <BaseSelectField
        :model-value="String(modelValue.sender || '')"
        :options="senderOptions"
        label="Sender"
        @update:model-value="update('sender', $event)"
      />
    </FieldGroup>

    <FieldGroup title="Recipient" description="Phone number or a placeholder that resolves to one.">
      <BaseTextField
        :model-value="String(modelValue.receiver || '')"
        label="Recipient"
        placeholder="{{ wc_billing_phone }} or +5511999990000"
        @update:model-value="update('receiver', $event)"
      />
    </FieldGroup>

    <FieldGroup title="Media">
      <BaseSelectField
        :model-value="String(modelValue.media_type || 'image')"
        :options="mediaTypeOptions"
        label="Media type"
        @update:model-value="update('media_type', $event)"
      />
      <div class="flex items-end gap-2">
        <div class="flex-1">
          <BaseTextField
            :model-value="String(modelValue.media_url || '')"
            label="Media URL"
            type="url"
            placeholder="https://example.com/file.jpg"
            @update:model-value="update('media_url', $event)"
          />
        </div>
        <button
          type="button"
          class="mb-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
          @click="openMediaLibrary"
        >
          <i class="bx bx-image-add text-base" />
          Library
        </button>
      </div>
    </FieldGroup>

    <FieldGroup v-if="!isAudio" title="Caption" description="Optional text sent together with the media.">
      <BaseTextareaField
        :model-value="String(modelValue.caption || '')"
        label="Caption"
        :rows="4"
        placeholder="Media caption... Use {{ placeholders }}"
        @update:model-value="update('caption', $event)"
      />
    </FieldGroup>

    <PlaceholderList
      v-if="!isAudio && Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      title="Available placeholders"
      @select="insertPlaceholder"
    />
  </div>
</template>
