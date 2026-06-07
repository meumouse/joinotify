<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseDateField from '../../components/base/BaseDateField.vue';
import BaseNumberField from '../../components/base/BaseNumberField.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTimeField from '../../components/base/BaseTimeField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const typeOptions = [
  { label: __('Period', textDomain), value: 'period' },
  { label: __('Date', textDomain), value: 'date' },
];

const periodOptions = [
  { label: __('Seconds', textDomain), value: 'seconds' },
  { label: __('Minutes', textDomain), value: 'minute' },
  { label: __('Hours', textDomain), value: 'hours' },
  { label: __('Days', textDomain), value: 'day' },
  { label: __('Weeks', textDomain), value: 'week' },
  { label: __('Months', textDomain), value: 'month' },
  { label: __('Years', textDomain), value: 'year' },
];

function update(key: string, value: unknown) {
  emit('update:modelValue', {
    ...(props.modelValue as Record<string, unknown>),
    [key]: value,
  });
}
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      v-if="!cronAvailable"
      tone="warning"
      :title="__('Cron unavailable', textDomain)"
      :message="__('This delay uses scheduled execution. The workflow environment does not report cron as available.', textDomain)"
    />

    <FieldGroup :title="__('Delay mode', textDomain)" :description="__('Choose whether the workflow should wait for a period or a fixed date.', textDomain)">
      <BaseSelectField
        :model-value="String(modelValue.delay_type || 'period')"
        :options="typeOptions"
        :label="__('Type', textDomain)"
        @update:model-value="update('delay_type', $event)"
      />
    </FieldGroup>

    <FieldGroup v-if="String(modelValue.delay_type || 'period') === 'period'" :title="__('Period delay', textDomain)">
      <BaseNumberField
        :model-value="modelValue.delay_value ?? ''"
        :label="__('Amount', textDomain)"
        placeholder="5"
        @update:model-value="update('delay_value', $event)"
      />
      <BaseSelectField
        :model-value="String(modelValue.delay_period || 'minute')"
        :options="periodOptions"
        :label="__('Period', textDomain)"
        @update:model-value="update('delay_period', $event)"
      />
    </FieldGroup>

    <FieldGroup v-else :title="__('Scheduled date', textDomain)">
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseDateField
          :model-value="String(modelValue.date_value || '')"
          :label="__('Date', textDomain)"
          @update:model-value="update('date_value', $event)"
        />
        <BaseTimeField
          :model-value="String(modelValue.time_value || '')"
          :label="__('Time', textDomain)"
          @update:model-value="update('time_value', $event)"
        />
      </div>
    </FieldGroup>

    <PlaceholderList
      v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      :title="__('Available placeholders', textDomain)"
      @select="$emit('placeholder-selected', $event)"
    />
  </div>
</template>
