<script setup lang="ts">
/**
 * WhatsappMediaSettings.vue
 *
 * Settings panel for the "WhatsApp media" action. Configures the sender,
 * recipient, media type and URL (with a WordPress media library picker) and an
 * optional caption for image, video, document or audio messages.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextFieldVariables from '../../components/base/BaseTextFieldVariables.vue';
import BaseRichTextArea from '../../../components/base/BaseRichTextArea.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useSenderOptions } from '../../../composables/useSenderOptions';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { ImagePlus } from '@boxicons/vue';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const mediaTypeOptions = [
  { label: __('Image', textDomain), value: 'image' },
  { label: __('Video', textDomain), value: 'video' },
  { label: __('Document', textDomain), value: 'document' },
  { label: __('Audio', textDomain), value: 'audio' },
];

const senderOptions = useSenderOptions(() => (props.modelValue as Record<string, unknown>).sender);

const isAudio = computed(() => String((props.modelValue as Record<string, unknown>).media_type || 'image') === 'audio');

const { update } = useActionSettingsUpdate(props, emit);

/**
 * Open the WordPress media library frame and store the selected attachment URL
 * on the action model.
 *
 * @since 2.0.0
 */
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

  const frame = wpMedia({ title: __('Select media', textDomain), button: { text: __('Use this media', textDomain) }, multiple: false });

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
    <FieldGroup :title="__('Sender', textDomain)" :description="__('WhatsApp number that will send the media.', textDomain)">
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

    <FieldGroup :title="__('Media', textDomain)">
      <BaseSelectField
        :model-value="String(modelValue.media_type || 'image')"
        :options="mediaTypeOptions"
        :label="__('Media type', textDomain)"
        @update:model-value="update('media_type', $event)"
      />
      <div class="flex items-end gap-2">
        <div class="flex-1">
          <BaseTextField
            :model-value="String(modelValue.media_url || '')"
            :label="__('Media URL', textDomain)"
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
          <ImagePlus class="text-base" />
          {{ __('Library', textDomain) }}
        </button>
      </div>
    </FieldGroup>

    <FieldGroup v-if="!isAudio" :title="__('Caption', textDomain)" :description="__('Optional text sent together with the media.', textDomain)">
      <BaseRichTextArea
        :model-value="String(modelValue.caption || '')"
        :label="__('Caption', textDomain)"
        :rows="4"
        :placeholder="__('Media caption... Use {{ placeholders }}', textDomain)"
        :placeholders="availablePlaceholders"
        @update:model-value="update('caption', $event)"
      />
    </FieldGroup>
  </div>
</template>
