import StopFunnelSettings from '../settings/StopFunnelSettings.vue';
import { describeStopAction, truncateDescription } from '../utils/actionDescription';
import type { ActionDefinition } from '../registry/types';
import { STOP_FUNNEL_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

function normalizeStopFunnelData(data: Record<string, unknown>): Record<string, unknown> {
  return {
    title: String(data.title || __('Stop funnel', textDomain)),
    description: String(data.description || ''),
    action: 'stop_funnel',
    settings: data.settings && typeof data.settings === 'object' ? data.settings : {},
  };
}

export const stopFunnelDefinition: ActionDefinition = {
  action: 'stop_funnel',
  title: __('Stop funnel', textDomain),
  description: __('End the workflow at this point.', textDomain),
  icon: 'ban',
  iconSvg: STOP_FUNNEL_ICON,
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
