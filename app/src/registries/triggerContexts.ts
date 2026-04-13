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

export function getTriggerContextById(id: string): WorkflowContextDefinition | undefined {
  return TRIGGER_CONTEXTS.find((item) => item.id === id);
}
