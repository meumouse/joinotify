import StopFunnelSettings from '../settings/StopFunnelSettings.vue';
import { describeStopAction, truncateDescription } from '../utils/actionDescription';
import type { ActionDefinition } from '../registry/types';

function normalizeStopFunnelData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || 'Stop funnel'),
    description: String(data.description || ''),
    action: 'stop_funnel',
    settings: data.settings && typeof data.settings === 'object' ? data.settings : {},
  };
}

export const stopFunnelDefinition: ActionDefinition = {
  action: 'stop_funnel',
  title: 'Stop funnel',
  description: 'End the workflow at this point.',
  icon: 'ban',
  hasSettings: false,
  priority: 40,
  isExpansible: false,
  defaultData: normalizeStopFunnelData({}),
  settingsComponent: StopFunnelSettings,
  normalizeData: normalizeStopFunnelData,
  serializeData: normalizeStopFunnelData,
  buildDescription: () => truncateDescription(describeStopAction()),
  validate: () => ({}),
};
