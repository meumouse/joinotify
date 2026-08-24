<script setup>

/**
 * PhonesSettingsSection.vue frontend component.
 *
 * @since 1.4.7
 * @version 2.3.0
 */
import { computed } from 'vue';
import PhoneActions from '../cards/PhoneActions.vue';
import PhoneSenderList from '../cards/PhoneSenderList.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  phones: { type: Object, default: () => ({ senders: [], sender_count: 0 }) },
  refreshingSenderPhone: { type: String, default: '' },
  senderActionLoading: { type: Boolean, default: false },
  defaultCountry: { type: String, default: 'us' },
  locale: { type: String, default: 'en_US' },
  sendTestMessage: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'remove', 'refresh', 'sync']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <div class="space-y-10">
    <PhoneActions
      v-model="model"
      :senders="phones.senders || []"
      :default-country="defaultCountry"
      :locale="locale"
      :sender-action-loading="senderActionLoading"
      :send-test-message="sendTestMessage"
      :panel-url="phones.panel_url || ''"
      @sync="$emit('sync')"
    />

    <PhoneSenderList
      :senders="phones.senders || []"
      :refreshing-phone="refreshingSenderPhone"
      :last-sync="phones.last_sync || 0"
      @remove="$emit('remove', $event)"
      @refresh="$emit('refresh', $event)"
    />
  </div>
</template>
