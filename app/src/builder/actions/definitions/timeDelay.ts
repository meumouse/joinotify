import TimeDelaySettings from '../settings/TimeDelaySettings.vue';
import { describeTimeDelayAction, truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { TIME_DELAY_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

function normalizeTimeDelayData(data: Record<string, unknown>): Record<string, unknown> {
  const delayValue = data.delay_value ?? 1;
  const delayTimestamp = data.delay_timestamp ?? '';

  return {
    title: String(data.title || __('Delay', textDomain)),
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
  title: __('Delay', textDomain),
  description: __('Pause the workflow before the next step.', textDomain),
  icon: 'clock',
  iconSvg: TIME_DELAY_ICON,
  hasSettings: true,
  priority: 90,
  isExpansible: false,
  defaultData: normalizeTimeDelayData({}),
  settingsComponent: TimeDelaySettings,
  settingsSchema: [
    { key: 'delay_type', label: __('Delay type', textDomain), component: 'select', required: true, options: [
      { label: __('Period', textDomain), value: 'period' },
      { label: __('Date', textDomain), value: 'date' },
      { label: __('Scheduled', textDomain), value: 'scheduled' },
    ] },
    { key: 'delay_value', label: __('Amount', textDomain), component: 'number', componentProps: { min: 1 } },
    { key: 'delay_period', label: __('Period', textDomain), component: 'select', options: [
      { label: __('Seconds', textDomain), value: 'seconds' },
      { label: __('Minutes', textDomain), value: 'minute' },
      { label: __('Hours', textDomain), value: 'hours' },
      { label: __('Days', textDomain), value: 'day' },
      { label: __('Weeks', textDomain), value: 'week' },
      { label: __('Months', textDomain), value: 'month' },
      { label: __('Years', textDomain), value: 'year' },
    ] },
    { key: 'date_value', label: __('Date', textDomain), component: 'date' },
    { key: 'time_value', label: __('Time', textDomain), component: 'time' },
  ],
  normalizeData: normalizeTimeDelayData,
  serializeData: normalizeTimeDelayData,
  buildDescription: (data) => truncateDescription(describeTimeDelayAction(data)),
  validate: (data) => {
    const delayType = String(data.delay_type || 'period');

    if (delayType === 'date') {
      return normalizeValidationErrors(requiredFieldErrors(data, [
        { key: 'date_value', label: __('Date', textDomain) },
        { key: 'time_value', label: __('Time', textDomain) },
      ]));
    }

    if (delayType === 'scheduled') {
      return normalizeValidationErrors(requiredFieldErrors(data, [
        { key: 'delay_value', label: __('Amount', textDomain) },
        { key: 'delay_period', label: __('Period', textDomain) },
        { key: 'time_value', label: __('Time', textDomain) },
      ]));
    }

    return normalizeValidationErrors(requiredFieldErrors(data, [
      { key: 'delay_value', label: __('Amount', textDomain) },
      { key: 'delay_period', label: __('Period', textDomain) },
    ]));
  },
};
