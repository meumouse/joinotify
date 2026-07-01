/**
 * stopFunnel.ts
 *
 * Builder action definition for the "Stop funnel" action, which ends the
 * workflow at its point in the flow. Provides data normalization and a static
 * description; it has no configurable settings.
 *
 * @since 2.0.0
 */
import StopFunnelSettings from '../settings/StopFunnelSettings.vue';
import { describeStopAction, truncateDescription } from '../utils/actionDescription';
import type { ActionDefinition } from '../registry/types';
import { STOP_FUNNEL_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Normalizes/serializes the stop-funnel action payload, applying defaults for
 * the title and settings object.
 *
 * @since 2.0.0
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized stop-funnel action data.
 */
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
