import { __, textDomain } from '../utils/i18n';
import { cloneSerializable } from '../utils/workflowTree';
import { TRIGGER_CONTEXTS } from './triggerContexts';
import type { WorkflowContextDefinition, WorkflowFieldCondition, WorkflowFieldSchema, WorkflowRegistryItem } from '../types/workflowBuilder';

type BackendTriggerRecord = Record<string, unknown>;

function ensureArray(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((item) => String(item)).filter(Boolean);
  }

  if (typeof value === 'string' && value.trim()) {
    return [value.trim()];
  }

  return [];
}

function normalizeFieldOption(option: unknown) {
  if (!option || typeof option !== 'object') {
    return null;
  }

  const source = option as Record<string, unknown>;
  const value = source.value ?? source.id ?? source.key ?? source.slug;

  if (typeof value === 'undefined') {
    return null;
  }

  return {
    label: String(source.label || source.title || value),
    value,
    description: typeof source.description === 'string' ? source.description : '',
    disabled: Boolean(source.disabled),
    icon: typeof source.icon === 'string' ? source.icon : '',
  };
}

function normalizeFieldSchema(field: unknown): WorkflowFieldSchema | null {
  if (!field || typeof field !== 'object') {
    return null;
  }

  const source = field as Record<string, unknown>;
  const component = String(source.component || source.type || 'input').toLowerCase();
  const nestedFields = Array.isArray(source.fields) ? source.fields.map(normalizeFieldSchema).filter(Boolean) as WorkflowFieldSchema[] : [];
  const options = Array.isArray(source.options) ? source.options.map(normalizeFieldOption).filter(Boolean) as NonNullable<WorkflowFieldSchema['options']> : [];

  return {
    key: String(source.key || source.name || source.id || ''),
    label: String(source.label || source.title || source.key || ''),
    component: component as WorkflowFieldSchema['component'],
    placeholder: typeof source.placeholder === 'string' ? source.placeholder : '',
    description: typeof source.description === 'string' ? source.description : '',
    helper: typeof source.helper === 'string' ? source.helper : '',
    required: Boolean(source.required),
    rows: Number(source.rows || 0) || undefined,
    defaultValue: source.defaultValue,
    options,
    fields: nestedFields.length ? nestedFields : undefined,
    repeatable: Boolean(source.repeatable),
    minItems: Number(source.minItems || 0) || undefined,
    maxItems: Number(source.maxItems || 0) || undefined,
    condition: Array.isArray(source.condition)
      ? source.condition
          .filter((item) => item && typeof item === 'object')
          .map((item) => ({
            key: String((item as Record<string, unknown>).key || ''),
            value: (item as Record<string, unknown>).value,
            operator: (item as Record<string, unknown>).operator as WorkflowFieldCondition['operator'],
          }))
          .filter((item) => item.key)
      : undefined,
    componentProps: source.componentProps && typeof source.componentProps === 'object' ? cloneSerializable(source.componentProps) : undefined,
  };
}

function normalizeSchema(fields: unknown): WorkflowFieldSchema[] {
  if (!Array.isArray(fields)) {
    return [];
  }

  return fields.map(normalizeFieldSchema).filter(Boolean) as WorkflowFieldSchema[];
}

function mergeSchema(primary: WorkflowFieldSchema[] | undefined, fallback: WorkflowFieldSchema[]): WorkflowFieldSchema[] {
  if (!primary || primary.length === 0) {
    return cloneSerializable(fallback);
  }

  return primary;
}

function normalizeContexts(rawContext: unknown): string[] {
  if (Array.isArray(rawContext)) {
    return rawContext.map((item) => String(item)).filter(Boolean);
  }

  if (typeof rawContext === 'string' && rawContext.trim()) {
    return [rawContext.trim()];
  }

  return [];
}

function createTriggerDefinition(
  id: string,
  label: string,
  description: string,
  icon: string,
  context: string,
  schema: WorkflowFieldSchema[] = [],
  options: Partial<WorkflowRegistryItem> = {}
): WorkflowRegistryItem {
  return {
    id,
    label,
    description,
    icon,
    iconSvg: options.iconSvg,
    context,
    contexts: [context],
    category: options.category || context,
    schema,
    settingsComponent: options.settingsComponent,
    parseData: options.parseData,
    serializeData: options.serializeData,
    preview: options.preview,
    validate: options.validate,
    requireSettings: options.requireSettings,
    enabled: options.enabled,
  };
}

function cloneTriggerDefinition(definition: WorkflowRegistryItem): WorkflowRegistryItem {
  return {
    ...definition,
    schema: cloneSerializable(definition.schema),
    context: Array.isArray(definition.context)
      ? [...definition.context]
      : typeof definition.context === 'string'
        ? definition.context
        : definition.context,
    contexts: Array.isArray(definition.contexts) ? [...definition.contexts] : [],
  };
}

function normalizeTriggerData(data: Record<string, unknown>, defaults: Record<string, unknown>) {
  return {
    ...cloneSerializable(defaults),
    ...cloneSerializable(data),
    title: typeof data.title === 'string' ? data.title : String(defaults.title || ''),
    description: typeof data.description === 'string' ? data.description : String(defaults.description || ''),
    trigger: typeof data.trigger === 'string' ? data.trigger : String(defaults.trigger || ''),
    context: typeof data.context === 'string' ? data.context : String(defaults.context || ''),
    settings: data.settings && typeof data.settings === 'object' ? cloneSerializable(data.settings) : cloneSerializable(defaults.settings || {}),
  };
}

const TRIGGER_FALLBACKS: WorkflowRegistryItem[] = [
  createTriggerDefinition(
    'woocommerce_order_status_completed',
    __('Order completed', textDomain),
    __('Trigger when an order reaches completed status.', textDomain),
    'shopping-cart',
    'woocommerce',
    [
      { key: 'title', label: __('Workflow title', textDomain), component: 'input', placeholder: __('Workflow name', textDomain), required: true },
      { key: 'description', label: __('Description', textDomain), component: 'textarea', rows: 4, placeholder: __('Internal summary', textDomain) },
    ],
    {
      preview: (data) => `${String(data.title || __('Trigger', textDomain))} · ${String(data.trigger || 'woocommerce_order_status_completed')}`,
      parseData: (data) => normalizeTriggerData(data, {
        title: __('Order completed', textDomain),
        description: '',
        trigger: 'woocommerce_order_status_completed',
        context: 'woocommerce',
        settings: {},
      }),
      serializeData: (data) => normalizeTriggerData(data, {
        title: __('Order completed', textDomain),
        description: '',
        trigger: 'woocommerce_order_status_completed',
        context: 'woocommerce',
        settings: {},
      }),
      requireSettings: false,
    }
  ),
  createTriggerDefinition(
    'woocommerce_order_status_changed',
    __('Order status changed', textDomain),
    __('Trigger when an order status changes.', textDomain),
    'shuffle',
    'woocommerce',
    [
      { key: 'title', label: __('Workflow title', textDomain), component: 'input', placeholder: __('Workflow name', textDomain), required: true },
      { key: 'description', label: __('Description', textDomain), component: 'textarea', rows: 4, placeholder: __('Internal summary', textDomain) },
    ],
    {
      preview: (data) => `${String(data.title || __('Trigger', textDomain))} · ${String(data.trigger || 'woocommerce_order_status_changed')}`,
      parseData: (data) => normalizeTriggerData(data, {
        title: __('Order status changed', textDomain),
        description: '',
        trigger: 'woocommerce_order_status_changed',
        context: 'woocommerce',
        settings: {},
      }),
      serializeData: (data) => normalizeTriggerData(data, {
        title: __('Order status changed', textDomain),
        description: '',
        trigger: 'woocommerce_order_status_changed',
        context: 'woocommerce',
        settings: {},
      }),
      requireSettings: true,
    }
  ),
  createTriggerDefinition(
    'wordpress_post_published',
    __('Post published', textDomain),
    __('Trigger when a WordPress post is published.', textDomain),
    'wordpress',
    'wordpress',
    [
      { key: 'title', label: __('Workflow title', textDomain), component: 'input', placeholder: __('Workflow name', textDomain), required: true },
      { key: 'description', label: __('Description', textDomain), component: 'textarea', rows: 4, placeholder: __('Internal summary', textDomain) },
    ],
    {
      preview: (data) => `${String(data.title || __('Trigger', textDomain))} · ${String(data.trigger || 'wordpress_post_published')}`,
      parseData: (data) => normalizeTriggerData(data, {
        title: __('Post published', textDomain),
        description: '',
        trigger: 'wordpress_post_published',
        context: 'wordpress',
        settings: {},
      }),
      serializeData: (data) => normalizeTriggerData(data, {
        title: __('Post published', textDomain),
        description: '',
        trigger: 'wordpress_post_published',
        context: 'wordpress',
        settings: {},
      }),
      requireSettings: false,
    }
  ),
];

function normalizeContextDefinition(raw: BackendTriggerRecord): WorkflowContextDefinition | null {
  const id = String(raw.id || raw.slug || raw.context || raw.category || '').trim();

  if (!id) {
    return null;
  }

  return {
    id,
    label: String(raw.label || raw.title || id),
    description: String(raw.description || ''),
    icon: String(raw.icon || id),
    icon_svg: String(raw.icon_svg || raw.iconSvg || ''),
    icon_url: String(raw.icon_url || raw.iconUrl || raw.logo || ''),
    category: String(raw.category || id),
    enabled: raw.enabled === undefined ? true : Boolean(raw.enabled),
  };
}

function normalizeTriggerRecord(context: string, raw: BackendTriggerRecord): WorkflowRegistryItem | null {
  const triggerId = String(raw.data_trigger || raw.trigger || raw.id || raw.slug || '').trim();

  if (!triggerId) {
    return null;
  }

  const fallback = TRIGGER_FALLBACKS.find((item) => item.id === triggerId && ensureArray(item.context).includes(context));
  const schema = normalizeSchema(raw.settings || raw.schema || raw.fields);
  const contexts = normalizeContexts(raw.context || context);

  return {
    id: triggerId,
    label: String(raw.title || raw.label || fallback?.label || triggerId),
    description: String(raw.description || fallback?.description || ''),
    icon: String(raw.icon || fallback?.icon || context),
    iconSvg: String(raw.icon_svg || raw.iconSvg || fallback?.iconSvg || ''),
    context: contexts.length ? contexts : [context],
    contexts: contexts.length ? contexts : [context],
    category: String(raw.category || fallback?.category || context),
    schema: mergeSchema(schema, fallback?.schema || []),
    settingsComponent: String(raw.settings_component || raw.settingsComponent || fallback?.settingsComponent || ''),
    parseData: fallback?.parseData,
    serializeData: fallback?.serializeData,
    preview: fallback?.preview,
    validate: fallback?.validate,
    requireSettings: Boolean(raw.require_settings ?? raw.requireSettings ?? fallback?.requireSettings),
    enabled: raw.enabled === undefined ? fallback?.enabled : Boolean(raw.enabled),
  };
}

let activeTriggerCatalog: Record<string, WorkflowRegistryItem[]> = {};
let activeTriggerContexts: WorkflowContextDefinition[] = TRIGGER_CONTEXTS.map((item) => cloneSerializable(item));

export function normalizeTriggerCatalog(rawTriggers: Record<string, Array<Record<string, unknown>>> = {}, rawContexts: Array<Record<string, unknown>> = []) {
  const grouped: Record<string, WorkflowRegistryItem[]> = {};
  const allContexts: WorkflowContextDefinition[] = [];
  const fallbackContextMap = new Map(TRIGGER_CONTEXTS.map((item) => [item.id, item]));

  const sourceContexts = rawContexts.length
    ? rawContexts.map(normalizeContextDefinition).filter(Boolean) as WorkflowContextDefinition[]
    : cloneSerializable(TRIGGER_CONTEXTS);

  for (const context of sourceContexts) {
    allContexts.push({
      ...context,
      enabled: context.enabled !== false,
    });
  }

  for (const [context, triggers] of Object.entries(rawTriggers || {})) {
    const normalizedTriggers = triggers
      .map((trigger) => normalizeTriggerRecord(context, trigger))
      .filter(Boolean) as WorkflowRegistryItem[];

    const fallbackTriggers = TRIGGER_FALLBACKS.filter((item) => ensureArray(item.context).includes(context));
    const merged = new Map<string, WorkflowRegistryItem>();

    for (const fallback of fallbackTriggers) {
      merged.set(fallback.id, cloneTriggerDefinition(fallback));
    }

    for (const trigger of normalizedTriggers) {
      const existing = merged.get(trigger.id);
      merged.set(trigger.id, {
        ...existing,
        ...trigger,
        schema: trigger.schema.length ? trigger.schema : existing?.schema || [],
        contexts: trigger.contexts.length ? trigger.contexts : existing?.contexts || [],
        context: trigger.contexts.length ? trigger.contexts : existing?.context || [],
        parseData: trigger.parseData || existing?.parseData,
        serializeData: trigger.serializeData || existing?.serializeData,
        preview: trigger.preview || existing?.preview,
        validate: trigger.validate || existing?.validate,
      });
    }

    grouped[context] = Array.from(merged.values()).map((item) => cloneTriggerDefinition(item));
  }

  for (const fallback of TRIGGER_FALLBACKS) {
    const fallbackContexts = ensureArray(fallback.context);

    for (const context of fallbackContexts) {
      if (!grouped[context]) {
        grouped[context] = [];
      }

      if (!grouped[context].some((item) => item.id === fallback.id)) {
        grouped[context].push(cloneTriggerDefinition(fallback));
      }

      if (!allContexts.some((item) => item.id === context)) {
        const contextDefinition = fallbackContextMap.get(context) || {
          id: context,
          label: context,
          description: '',
          icon: context,
          category: context,
        };

        allContexts.push({
          ...cloneSerializable(contextDefinition),
          enabled: true,
        });
      }
    }
  }

  return {
    catalog: grouped,
    contexts: allContexts,
  };
}

export function setTriggerCatalog(
  rawTriggers: Record<string, Array<Record<string, unknown>>> = {},
  rawContexts: Array<Record<string, unknown>> = []
) {
  const normalized = normalizeTriggerCatalog(rawTriggers, rawContexts);
  activeTriggerCatalog = normalized.catalog;
  activeTriggerContexts = normalized.contexts;
}

export function getTriggerCatalog(): Record<string, WorkflowRegistryItem[]> {
  return Object.fromEntries(
    Object.entries(activeTriggerCatalog).map(([key, value]) => [key, value.map((item) => cloneTriggerDefinition(item))])
  );
}

export function getTriggerContextsCatalog(): WorkflowContextDefinition[] {
  return cloneSerializable(activeTriggerContexts.length ? activeTriggerContexts : TRIGGER_CONTEXTS);
}

export function getTriggerContextDefinition(contextId: string): WorkflowContextDefinition | undefined {
  const safeContextId = String(contextId || '').trim();

  if (!safeContextId) {
    return undefined;
  }

  return getTriggerContextsCatalog().find((context) => context.id === safeContextId);
}

export const TRIGGER_REGISTRY: WorkflowRegistryItem[] = TRIGGER_FALLBACKS;

export function getTriggersForContext(context: string): WorkflowRegistryItem[] {
  const safeContext = String(context || '').trim();

  if (!safeContext) {
    return Object.values(activeTriggerCatalog).flatMap((items) => items.map((item) => cloneTriggerDefinition(item)));
  }

  return activeTriggerCatalog[safeContext]
    ? activeTriggerCatalog[safeContext].map((item) => cloneTriggerDefinition(item))
    : TRIGGER_FALLBACKS.filter((item) => ensureArray(item.context).includes(safeContext)).map((item) => cloneTriggerDefinition(item));
}

export function getTriggerDefinition(context: string, triggerId: string): WorkflowRegistryItem | undefined {
  const safeContext = String(context || '').trim();
  const safeTrigger = String(triggerId || '').trim();

  const grouped = activeTriggerCatalog[safeContext] || [];
  const found = grouped.find((item) => item.id === safeTrigger);

  if (found) {
    return found;
  }

  return TRIGGER_FALLBACKS.find((item) => item.id === safeTrigger && ensureArray(item.context).includes(safeContext));
}

export function getTriggerDefinitionFromNode(node: { data?: Record<string, unknown> } | null | undefined): WorkflowRegistryItem | undefined {
  const context = String(node?.data?.context || '');
  const trigger = String(node?.data?.trigger || '');

  if (!context || !trigger) {
    return undefined;
  }

  return getTriggerDefinition(context, trigger);
}
