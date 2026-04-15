import TimeDelaySettings from '../settings/TimeDelaySettings.vue';
import { describeTimeDelayAction, truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';

function normalizeTimeDelayData(data: Record<string, unknown>): Record<string, unknown> {
  const delayValue = data.delay_value ?? 1;
  const delayTimestamp = data.delay_timestamp ?? '';

  return {
    title: String(data.title || 'Delay'),
    description: String(data.description || ''),
    action: 'time_delay',
    delay_type: String(data.delay_type || 'period'),
    delay_value: delayValue,
    delay_period: String(data.delay_period || 'minute'),
    date_value: String(data.date_value || ''),
    time_value: String(data.time_value || ''),
    delay_timestamp: delayTimestamp,
    settings: data.settings && typeof data.settings === 'object' ? data.settings : {},
  };
}

export const timeDelayDefinition: ActionDefinition = {
  action: 'time_delay',
  title: 'Delay',
  description: 'Pause the workflow before the next step.',
  icon: 'clock',
  hasSettings: true,
  priority: 90,
  isExpansible: false,
  defaultData: normalizeTimeDelayData({}),
  settingsComponent: TimeDelaySettings,
  settingsSchema: [
    { key: 'delay_type', label: 'Delay type', component: 'select', required: true, options: [
      { label: 'Period', value: 'period' },
      { label: 'Date', value: 'date' },
    ] },
    { key: 'delay_value', label: 'Amount', component: 'number', componentProps: { min: 1 } },
    { key: 'delay_period', label: 'Period', component: 'select', options: [
      { label: 'Seconds', value: 'seconds' },
      { label: 'Minutes', value: 'minute' },
      { label: 'Hours', value: 'hours' },
      { label: 'Days', value: 'day' },
      { label: 'Weeks', value: 'week' },
      { label: 'Months', value: 'month' },
      { label: 'Years', value: 'year' },
    ] },
    { key: 'date_value', label: 'Date', component: 'date' },
    { key: 'time_value', label: 'Time', component: 'time' },
  ],
  normalizeData: normalizeTimeDelayData,
  serializeData: normalizeTimeDelayData,
  buildDescription: (data) => truncateDescription(describeTimeDelayAction(data)),
  validate: (data) => {
    const delayType = String(data.delay_type || 'period');

    if (delayType === 'date') {
      return normalizeValidationErrors(requiredFieldErrors(data, [
        { key: 'date_value', label: 'Date' },
        { key: 'time_value', label: 'Time' },
      ]));
    }

    return normalizeValidationErrors(requiredFieldErrors(data, [
      { key: 'delay_value', label: 'Amount' },
      { key: 'delay_period', label: 'Period' },
    ]));
  },
};
