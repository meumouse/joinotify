import { __, textDomain } from '../utils/i18n';
import type { WorkflowRegistryItem } from '../types/workflowBuilder';

const triggerFields = [
  { key: 'title', label: __('Workflow title', textDomain), component: 'input' as const, placeholder: __('Workflow name', textDomain) },
  { key: 'description', label: __('Description', textDomain), component: 'textarea' as const, placeholder: __('Internal summary', textDomain) },
];

function preserveTriggerData(data: Record<string, unknown>) {
  return {
    ...data,
    title: typeof data.title === 'string' ? data.title : '',
    description: typeof data.description === 'string' ? data.description : '',
    trigger: typeof data.trigger === 'string' ? data.trigger : '',
    context: typeof data.context === 'string' ? data.context : '',
  };
}

function buildTriggerPreview(data: Record<string, unknown>) {
  const title = typeof data.title === 'string' && data.title.trim() ? data.title : __('Trigger', textDomain);
  const trigger = typeof data.trigger === 'string' ? data.trigger : '';
  const context = typeof data.context === 'string' ? data.context : '';

  return `${title} • ${context || __('context', textDomain)} / ${trigger || __('trigger', textDomain)}`;
}

export const TRIGGER_REGISTRY: WorkflowRegistryItem[] = [
  {
    id: 'woocommerce_order_status_completed',
    label: __('Order completed', textDomain),
    description: __('Triggers when the order status changes to completed.', textDomain),
    icon: 'check-circle',
    context: 'woocommerce',
    schema: triggerFields,
    settingsComponent: 'TriggerWooCommerceSettings',
    parseData: preserveTriggerData,
    serializeData: preserveTriggerData,
    preview: buildTriggerPreview,
  },
  {
    id: 'woocommerce_order_status_changed',
    label: __('Order status changed', textDomain),
    description: __('Triggers when the order changes status.', textDomain),
    icon: 'exchange-alt',
    context: 'woocommerce',
    schema: triggerFields,
    settingsComponent: 'TriggerWooCommerceSettings',
    parseData: preserveTriggerData,
    serializeData: preserveTriggerData,
    preview: buildTriggerPreview,
  },
];

export function getTriggersForContext(context: string): WorkflowRegistryItem[] {
  return TRIGGER_REGISTRY.filter((item) => item.context === context);
}

export function getTriggerDefinition(context: string, triggerId: string): WorkflowRegistryItem | undefined {
  return TRIGGER_REGISTRY.find((item) => item.context === context && item.id === triggerId);
}
