/**
 * loop.ts
 *
 * Builder action definition for the "Loop" action, an expansible control node
 * that iterates over a collection (order digital files, order purchased items or
 * a delimited placeholder list) and runs the actions nested in its body once per
 * item. The current item is exposed to the body through {{ loop_* }} placeholders
 * and, for order downloads, the "Current loop item file" attachment source.
 *
 * @since 2.1.1
 */
import LoopSettings from '../settings/LoopSettings.vue';
import { truncateDescription } from '../utils/actionDescription';
import { normalizeValidationErrors, requiredFieldErrors } from '../utils/validators';
import type { ActionDefinition } from '../registry/types';
import { LOOP_ICON } from './actionIcons';
import { __, textDomain } from '../../../utils/i18n';

/**
 * Type guard for a plain object (non-null, non-array).
 *
 * @since 2.1.1
 * @param {unknown} value Value to test.
 * @returns {boolean} True when value is a plain record.
 */
function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

/**
 * Normalizes/serializes the loop action payload, applying defaults for the
 * collection source, the placeholder list and its delimiter, and an optional cap.
 *
 * @since 2.1.1
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {Record<string, unknown>} Normalized loop action data.
 */
function normalizeLoopData(data: Record<string, unknown>): Record<string, unknown> {
  const source = String(data.loop_source || 'order_downloads');
  const maxItems = data.loop_max_items;

  return {
    title: String(data.title || __('Loop', textDomain)),
    description: String(data.description || ''),
    action: 'loop',
    loop_source: source,
    loop_list: String(data.loop_list || ''),
    loop_delimiter: String(data.loop_delimiter || ''),
    loop_max_items: maxItems === undefined || maxItems === null || maxItems === '' ? '' : Number(maxItems) || '',
    settings: isRecord(data.settings) ? data.settings : {},
  };
}

/**
 * Builds a short human description of the configured loop source.
 *
 * @since 2.1.1
 * @param {Record<string, unknown>} data Raw action data.
 * @returns {string} A one-line summary.
 */
function describeLoop(data: Record<string, unknown>): string {
  const source = String(data.loop_source || 'order_downloads');

  if (source === 'order_items') {
    return __('For each purchased item in the order', textDomain);
  }

  if (source === 'placeholder_list') {
    return __('For each line of the list', textDomain);
  }

  return __('For each digital file in the order', textDomain);
}

export const loopDefinition: ActionDefinition = {
  action: 'loop',
  title: __('Loop', textDomain),
  description: __('Iterate over a collection and run the nested actions once per item.', textDomain),
  icon: 'repeat',
  iconSvg: LOOP_ICON,
  category: 'general',
  hasSettings: true,
  priority: 25,
  isExpansible: true,
  defaultData: normalizeLoopData({}),
  settingsComponent: LoopSettings,
  branchKeys: ['action_loop'],
  branchLabels: {
    action_loop: __('Loop body', textDomain),
  },
  branchHandles: {
    action_loop: 'loop',
  },
  emitsOutputAfterBranches: true,
  normalizeData: normalizeLoopData,
  serializeData: normalizeLoopData,
  buildDescription: (data) => truncateDescription(describeLoop(data)),
  validate: (data) => {
    const required: Array<{ key: string; label: string }> = [{ key: 'loop_source', label: __('Collection', textDomain) }];

    // the placeholder-list source is only meaningful with a value to split
    if (String(data.loop_source || '') === 'placeholder_list') {
      required.push({ key: 'loop_list', label: __('List', textDomain) });
    }

    return normalizeValidationErrors(requiredFieldErrors(data, required));
  },
};
