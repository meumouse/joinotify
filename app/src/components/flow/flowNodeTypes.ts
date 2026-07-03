/**
 * flowNodeTypes.ts
 *
 * Node type definitions for the FlowCanvas.
 * Adapted from the React builder's nodeTypes.ts.
 *
 * @since 1.4.7
 */

export interface FlowNodeConfig {
  type: string;
  label: string;
  /** Boxicon class name (e.g. "bx-zap"). Use with <i class="bx {icon}"> */
  icon: string;
  /** Tailwind background color class applied to the icon badge */
  color: string;
  description: string;
  /** "_internal" hides the type from the sidebar */
  category: string;
}

export const FLOW_NODE_TYPES: FlowNodeConfig[] = [
  {
    type: 'trigger',
    label: 'Acionamento',
    icon: 'bx-zap',
    color: 'bg-amber-500',
    description: 'Inicia o fluxo da automação.',
    category: '_internal',
  },
  {
    type: 'wait-time',
    label: 'Esperar tempo',
    icon: 'bx-time',
    color: 'bg-amber-500',
    description: 'Permite definir um tempo de espera antes da ação ser executada.',
    category: 'Tempo de espera',
  },
  {
    type: 'wait-date',
    label: 'Esperar até uma data',
    icon: 'bx-calendar-event',
    color: 'bg-amber-500',
    description: 'Aguarda até uma data específica para continuar.',
    category: 'Tempo de espera',
  },
  {
    type: 'condition',
    label: 'Condição',
    icon: 'bx-git-branch',
    color: 'bg-blue-500',
    description: 'Permite definir uma condição para uma ação ser executada.',
    category: 'Condição',
  },
  {
    type: 'stop',
    label: 'Parar automação aqui',
    icon: 'bx-stop-circle',
    color: 'bg-red-500',
    description: 'Nenhuma ação será executada ao chegar nesse ponto.',
    category: 'Parar automação',
  },
  {
    type: 'whatsapp-text',
    label: 'WhatsApp: Mensagem de texto',
    icon: 'bxl-whatsapp',
    color: 'bg-emerald-500',
    description: 'Envie uma mensagem de texto com o WhatsApp.',
    category: 'WhatsApp',
  },
  {
    type: 'whatsapp-media',
    label: 'WhatsApp: Mensagem de mídia',
    icon: 'bxl-whatsapp',
    color: 'bg-emerald-500',
    description: 'Envie uma mensagem de mídia (imagem, vídeo, documento e áudio) com o WhatsApp.',
    category: 'WhatsApp',
  },
  {
    type: 'php-snippet',
    label: 'Snippet PHP',
    icon: 'bxl-php',
    color: 'bg-indigo-600',
    description: 'Execute um trecho de código PHP personalizado.',
    category: 'Código',
  },
];

/** Returns only the types visible in the sidebar (not _internal) */
export const SIDEBAR_NODE_TYPES = FLOW_NODE_TYPES.filter((n) => n.category !== '_internal');

/** Groups sidebar types by category */
export function groupSidebarNodeTypes(): Record<string, FlowNodeConfig[]> {
  return SIDEBAR_NODE_TYPES.reduce<Record<string, FlowNodeConfig[]>>((acc, n) => {
    (acc[n.category] ??= []).push(n);
    return acc;
  }, {});
}

export function getFlowNodeConfig(type: string): FlowNodeConfig | undefined {
  return FLOW_NODE_TYPES.find((n) => n.type === type);
}
