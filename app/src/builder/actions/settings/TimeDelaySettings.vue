<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseDateField from '../../components/base/BaseDateField.vue';
import BaseNumberField from '../../components/base/BaseNumberField.vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTimeField from '../../components/base/BaseTimeField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const typeOptions = [
  { label: 'Period', value: 'period' },
  { label: 'Date', value: 'date' },
];

const periodOptions = [
  { label: 'Seconds', value: 'seconds' },
  { label: 'Minutes', value: 'minute' },
  { label: 'Hours', value: 'hours' },
  { label: 'Days', value: 'day' },
  { label: 'Weeks', value: 'week' },
  { label: 'Months', value: 'month' },
  { label: 'Years', value: 'year' },
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
      title="Cron unavailable"
      message="This delay uses scheduled execution. The workflow environment does not report cron as available."
    />

    <FieldGroup title="Delay mode" description="Choose whether the workflow should wait for a period or a fixed date.">
      <BaseSelectField
        :model-value="String(modelValue.delay_type || 'period')"
        :options="typeOptions"
        label="Type"
        @update:model-value="update('delay_type', $event)"
      />
    </FieldGroup>

    <FieldGroup v-if="String(modelValue.delay_type || 'period') === 'period'" title="Period delay">
      <BaseNumberField
        :model-value="modelValue.delay_value ?? ''"
        label="Amount"
        placeholder="5"
        @update:model-value="update('delay_value', $event)"
      />
      <BaseSelectField
        :model-value="String(modelValue.delay_period || 'minute')"
        :options="periodOptions"
        label="Period"
        @update:model-value="update('delay_period', $event)"
      />
    </FieldGroup>

    <FieldGroup v-else title="Scheduled date">
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseDateField
          :model-value="String(modelValue.date_value || '')"
          label="Date"
          @update:model-value="update('date_value', $event)"
        />
        <BaseTimeField
          :model-value="String(modelValue.time_value || '')"
          label="Time"
          @update:model-value="update('time_value', $event)"
        />
      </div>
    </FieldGroup>

    <PlaceholderList
      v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      title="Available placeholders"
      @select="$emit('placeholder-selected', $event)"
    />
  </div>
</template>
