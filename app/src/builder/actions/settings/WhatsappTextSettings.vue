<script setup lang="ts">
import { computed } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseTextareaField from '../../components/base/BaseTextareaField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const store = useWorkflowBuilderStore();

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

  return [{ label: __('— Select a sender —', textDomain), value: '' }, ...options];
});

function update(key: string, value: unknown) {
  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    [key]: value,
  });
}

function insertPlaceholder(placeholder: string) {
  const current = String((props.modelValue as Record<string, unknown>).message || '');
  update('message', current ? `${current} ${placeholder}` : placeholder);
  emit('placeholder-selected', placeholder);
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
      <BaseTextField
        :model-value="String(modelValue.receiver || '')"
        :label="__('Recipient', textDomain)"
        :placeholder="__('{{ wc_billing_phone }} or +5511999990000', textDomain)"
        @update:model-value="update('receiver', $event)"
      />
    </FieldGroup>

    <FieldGroup :title="__('Message', textDomain)">
      <BaseTextareaField
        :model-value="String(modelValue.message || '')"
        :label="__('Message', textDomain)"
        :rows="6"
        :placeholder="__('Type your message... Use {{ placeholders }} and *bold*', textDomain)"
        @update:model-value="update('message', $event)"
      />
    </FieldGroup>

    <PlaceholderList
      v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      :title="__('Available placeholders', textDomain)"
      @select="insertPlaceholder"
    />
  </div>
</template>
