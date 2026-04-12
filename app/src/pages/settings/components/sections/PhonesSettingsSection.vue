<script setup>

/**
 * PhonesSettingsSection.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import PhoneActions from '../cards/PhoneActions.vue';
import PhoneSenderList from '../cards/PhoneSenderList.vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  phoneCandidates: { type: Array, default: () => [] },
  phones: { type: Object, default: () => ({ senders: [], sender_count: 0 }) },
  refreshingSenderPhone: { type: String, default: '' },
  defaultCountry: { type: String, default: 'us' },
  sendTestMessage: { type: Function, default: null },
});

const emit = defineEmits(['update:modelValue', 'register', 'validate', 'remove', 'refresh']);

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <div class="space-y-10">
    <PhoneActions
      v-model="model"
      :candidates="phoneCandidates"
      :senders="phones.senders || []"
      :default-country="defaultCountry"
      :send-test-message="sendTestMessage"
      @register="$emit('register', $event)"
      @validate="$emit('validate', $event)"
    />

    <PhoneSenderList
      :senders="phones.senders || []"
      :refreshing-phone="refreshingSenderPhone"
      @remove="$emit('remove', $event)"
      @refresh="$emit('refresh', $event)"
    />
  </div>
</template>
