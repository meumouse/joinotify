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
  { label: __('Scheduled', textDomain), value: 'scheduled' },
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

// Scheduled mode only supports day-based offsets anchored to a time of day.
const scheduledPeriodOptions = [
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

function changeDelayType(value: unknown) {
  const next = {
    ...(props.modelValue as Record<string, unknown>),
    delay_type: value,
  };

  // Scheduled offsets are day-based; coerce sub-day period units to a sane default.
  if (value === 'scheduled') {
    const current = String(props.modelValue?.delay_period || '');

    if (!scheduledPeriodOptions.some((option) => option.value === current)) {
      next.delay_period = 'day';
    }
  }

  emit('update:modelValue', next);
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

    <FieldGroup :title="__('Delay mode', textDomain)" :description="__('Choose whether the workflow should wait for a period, a fixed date, or a relative offset at a specific time.', textDomain)">
      <BaseSelectField
        :model-value="String(modelValue.delay_type || 'period')"
        :options="typeOptions"
        :label="__('Type', textDomain)"
        @update:model-value="changeDelayType($event)"
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

    <FieldGroup
      v-else-if="String(modelValue.delay_type) === 'scheduled'"
      :title="__('Scheduled delay', textDomain)"
      :description="__('Advance the date by the chosen offset, then run at the selected time of day (e.g. +1 day at 09:00).', textDomain)"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseNumberField
          :model-value="modelValue.delay_value ?? ''"
          :label="__('Amount', textDomain)"
          placeholder="1"
          @update:model-value="update('delay_value', $event)"
        />
        <BaseSelectField
          :model-value="String(modelValue.delay_period || 'day')"
          :options="scheduledPeriodOptions"
          :label="__('Period', textDomain)"
          @update:model-value="update('delay_period', $event)"
        />
      </div>
      <BaseTimeField
        :model-value="String(modelValue.time_value || '')"
        :label="__('Time', textDomain)"
        @update:model-value="update('time_value', $event)"
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
