/**
 * triggerContexts.ts
 *
 * Static catalog of built-in trigger contexts (WordPress, WooCommerce, and
 * supported integrations) used as defaults/fallbacks by the trigger registry.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../utils/i18n';
import type { WorkflowContextDefinition } from '../types/workflowBuilder';

export const TRIGGER_CONTEXTS: WorkflowContextDefinition[] = [
  {
    id: 'wordpress',
    label: 'WordPress',
    description: __('Triggers for the WordPress core.', textDomain),
    icon: 'wordpress',
    category: 'wordpress',
  },
  {
    id: 'woocommerce',
    label: 'WooCommerce',
    description: __('Orders, status changes and purchase events.', textDomain),
    icon: 'shopping-cart',
    category: 'woocommerce',
  },
  {
    id: 'flexify_checkout',
    label: 'Flexify Checkout',
    description: __('Flexify checkout events.', textDomain),
    icon: 'credit-card',
    category: 'flexify_checkout',
  },
  {
    id: 'elementor',
    label: 'Elementor',
    description: __('Form submissions and sends.', textDomain),
    icon: 'elementor',
    category: 'elementor',
  },
  {
    id: 'wpforms',
    label: 'WPForms',
    description: __('Form submissions and responses.', textDomain),
    icon: 'wpforms',
    category: 'wpforms',
  },
];

/**
 * Finds a built-in trigger context by its ID.
 *
 * @since 2.0.0
 * @param {string} id The context ID.
 * @returns {WorkflowContextDefinition|undefined} The context, or undefined.
 */
export function getTriggerContextById(id: string): WorkflowContextDefinition | undefined {
  return TRIGGER_CONTEXTS.find((item) => item.id === id);
}
