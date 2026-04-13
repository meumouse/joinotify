import { __, textDomain } from '../utils/i18n';
import type { WorkflowRegistryItem } from '../types/workflowBuilder';

const actionFields = [
  { key: 'title', label: __('Title', textDomain), component: 'input' as const, placeholder: __('Action title', textDomain) },
  { key: 'description', label: __('Description / preview', textDomain), component: 'textarea' as const, placeholder: __('Controlled HTML preview', textDomain) },
];

function preserveActionData(data: Record<string, unknown>) {
  return {
    ...data,
    title: typeof data.title === 'string' ? data.title : '',
    description: typeof data.description === 'string' ? data.description : '',
    action: typeof data.action === 'string' ? data.action : '',
    message: typeof data.message === 'string' ? data.message : '',
    sender: typeof data.sender === 'string' ? data.sender : '',
    receiver: typeof data.receiver === 'string' ? data.receiver : '',
  };
}

function buildActionPreview(data: Record<string, unknown>) {
  const title = typeof data.title === 'string' && data.title.trim() ? data.title : __('Action', textDomain);
  const message = typeof data.message === 'string' ? data.message : '';

  return message ? `${title} • ${message.slice(0, 80)}` : title;
}

export const ACTION_REGISTRY: WorkflowRegistryItem[] = [
  {
    id: 'send_whatsapp_message_text',
    label: __('WhatsApp: Text message', textDomain),
    description: __('Send a text message via WhatsApp.', textDomain),
    icon: 'message',
    context: 'woocommerce',
    schema: [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('WhatsApp: Text message', textDomain) },
      { key: 'message', label: __('Message', textDomain), component: 'textarea', placeholder: __('Write the message with {{ placeholders }}', textDomain) },
      { key: 'sender', label: __('Sender', textDomain), component: 'input', placeholder: __('Configured number or sender', textDomain) },
      { key: 'receiver', label: __('Recipient', textDomain), component: 'input', placeholder: '{{ wc_billing_phone }}' },
    ],
    settingsComponent: 'WhatsappTextActionSettings',
    parseData: preserveActionData,
    serializeData: preserveActionData,
    preview: buildActionPreview,
  },
];

export function getActionDefinition(actionId: string): WorkflowRegistryItem | undefined {
  return ACTION_REGISTRY.find((item) => item.id === actionId);
}
