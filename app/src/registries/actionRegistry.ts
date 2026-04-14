import { __, textDomain } from '../utils/i18n';
import { cloneSerializable, isConditionAction, isDelayAction, isPlaceholderAction, isSnippetAction, isStopAction } from '../utils/workflowTree';
import type { WorkflowFieldCondition, WorkflowFieldSchema, WorkflowNode, WorkflowRegistryItem } from '../types/workflowBuilder';

type BackendActionRecord = Record<string, unknown>;

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

function buildPreview(actionLabel: string, data: Record<string, unknown>, fallback = '') {
  const title = typeof data.title === 'string' && data.title.trim() ? data.title.trim() : actionLabel;
  const description = typeof data.description === 'string' && data.description.trim() ? data.description.trim() : '';

  if (description) {
    return `${title} · ${description}`;
  }

  if (fallback) {
    return `${title} · ${fallback}`;
  }

  return title;
}

function requiredFieldValidation(schema: WorkflowFieldSchema[], data: Record<string, unknown>): string[] {
  const errors: string[] = [];

  for (const field of schema) {
    if (field.component === 'group' && Array.isArray(field.fields)) {
      const groupValue = (data[field.key] as Record<string, unknown>) || {};
      errors.push(...requiredFieldValidation(field.fields, groupValue));
      continue;
    }

    if (field.component === 'repeater') {
      const items = Array.isArray(data[field.key]) ? (data[field.key] as Record<string, unknown>[]) : [];
      if (field.required && items.length === 0) {
        errors.push(`${field.label}: ${__('At least one item is required.', textDomain)}`);
      }

      if (Array.isArray(field.fields)) {
        items.forEach((item, index) => {
          const nestedErrors = requiredFieldValidation(field.fields || [], item || {});
          nestedErrors.forEach((message) => {
            errors.push(`${field.label} #${index + 1}: ${message}`);
          });
        });
      }

      continue;
    }

    if (!field.required) {
      continue;
    }

    const value = data[field.key];
    const empty = value === undefined || value === null || value === '';

    if (empty) {
      errors.push(`${field.label}: ${__('This field is required.', textDomain)}`);
    }
  }

  return errors;
}

function createBaseActionDefinition(
  id: string,
  label: string,
  description: string,
  icon: string,
  schema: WorkflowFieldSchema[],
  options: Partial<WorkflowRegistryItem> = {}
): WorkflowRegistryItem {
  return {
    id,
    label,
    description,
    icon,
    schema,
    context: options.context ?? [],
    contexts: Array.isArray(options.contexts) ? options.contexts : normalizeContexts(options.context),
    category: options.category || '',
    settingsComponent: options.settingsComponent,
    parseData: options.parseData,
    serializeData: options.serializeData,
    preview: options.preview,
    validate: options.validate,
    requireSettings: options.requireSettings,
    enabled: options.enabled,
    iconSvg: options.iconSvg,
  };
}

function cloneActionDefinition(definition: WorkflowRegistryItem): WorkflowRegistryItem {
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

function normalizeActionData(data: Record<string, unknown>, defaults: Record<string, unknown>) {
  return {
    ...cloneSerializable(defaults),
    ...cloneSerializable(data),
    title: typeof data.title === 'string' ? data.title : String(defaults.title || ''),
    description: typeof data.description === 'string' ? data.description : String(defaults.description || ''),
    action: typeof data.action === 'string' ? data.action : String(defaults.action || ''),
    message: typeof data.message === 'string' ? data.message : String(defaults.message || ''),
    sender: typeof data.sender === 'string' ? data.sender : String(defaults.sender || ''),
    receiver: typeof data.receiver === 'string' ? data.receiver : String(defaults.receiver || ''),
    settings: data.settings && typeof data.settings === 'object' ? cloneSerializable(data.settings) : cloneSerializable(defaults.settings || {}),
  };
}

const ACTION_FALLBACKS: WorkflowRegistryItem[] = [
  createBaseActionDefinition(
    'send_whatsapp_message_text',
    __('WhatsApp: Text message', textDomain),
    __('Send a text message through WhatsApp.', textDomain),
    'message-circle',
    [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('WhatsApp message', textDomain), required: true },
      { key: 'message', label: __('Message', textDomain), component: 'textarea', rows: 9, placeholder: __('Write the message using placeholders', textDomain), required: true },
      { key: 'sender', label: __('Sender', textDomain), component: 'input', placeholder: __('Configured sender', textDomain) },
      { key: 'receiver', label: __('Recipient', textDomain), component: 'input', placeholder: '{{ wc_billing_phone }}', required: true },
    ],
    {
      context: [],
      preview: (data) => buildPreview(__('WhatsApp', textDomain), data, typeof data.message === 'string' ? data.message.slice(0, 80) : ''),
      parseData: (data) => normalizeActionData(data, {
        title: __('WhatsApp: Text message', textDomain),
        description: '',
        action: 'send_whatsapp_message_text',
        message: '',
        sender: '',
        receiver: '',
        settings: {},
      }),
      serializeData: (data) => normalizeActionData(data, {
        title: __('WhatsApp: Text message', textDomain),
        description: '',
        action: 'send_whatsapp_message_text',
        message: '',
        sender: '',
        receiver: '',
        settings: {},
      }),
      validate: (data) => requiredFieldValidation(
        [
          { key: 'message', label: __('Message', textDomain), component: 'textarea', required: true },
          { key: 'receiver', label: __('Recipient', textDomain), component: 'input', required: true },
        ],
        data
      ),
    }
  ),
  createBaseActionDefinition(
    'send_whatsapp_message_media',
    __('WhatsApp: Media message', textDomain),
    __('Send a media message through WhatsApp.', textDomain),
    'image',
    [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('Media message', textDomain), required: true },
      { key: 'caption', label: __('Caption', textDomain), component: 'textarea', rows: 6, placeholder: __('Media caption', textDomain) },
      { key: 'media_type', label: __('Media type', textDomain), component: 'select', options: [
        { label: __('Image', textDomain), value: 'image' },
        { label: __('Video', textDomain), value: 'video' },
        { label: __('Audio', textDomain), value: 'audio' },
        { label: __('Document', textDomain), value: 'document' },
      ] },
      { key: 'media_url', label: __('Media URL', textDomain), component: 'input', placeholder: 'https://...' },
      { key: 'sender', label: __('Sender', textDomain), component: 'input', placeholder: __('Configured sender', textDomain) },
      { key: 'receiver', label: __('Recipient', textDomain), component: 'input', placeholder: '{{ wc_billing_phone }}', required: true },
    ],
    {
      context: [],
      preview: (data) => buildPreview(__('Media', textDomain), data, typeof data.caption === 'string' ? data.caption.slice(0, 80) : ''),
      parseData: (data) => normalizeActionData(data, {
        title: __('WhatsApp: Media message', textDomain),
        description: '',
        action: 'send_whatsapp_message_media',
        caption: '',
        media_type: 'image',
        media_url: '',
        sender: '',
        receiver: '',
        settings: {},
      }),
      serializeData: (data) => normalizeActionData(data, {
        title: __('WhatsApp: Media message', textDomain),
        description: '',
        action: 'send_whatsapp_message_media',
        caption: '',
        media_type: 'image',
        media_url: '',
        sender: '',
        receiver: '',
        settings: {},
      }),
      validate: (data) => requiredFieldValidation(
        [
          { key: 'media_url', label: __('Media URL', textDomain), component: 'input', required: true },
          { key: 'receiver', label: __('Recipient', textDomain), component: 'input', required: true },
        ],
        data
      ),
    }
  ),
  createBaseActionDefinition(
    'time_delay',
    __('Delay', textDomain),
    __('Pause the workflow before the next step.', textDomain),
    'clock',
    [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('Delay', textDomain), required: true },
      {
        key: 'delay_type',
        label: __('Delay type', textDomain),
        component: 'select',
        options: [
          { label: __('Wait for period', textDomain), value: 'period' },
          { label: __('Wait until date', textDomain), value: 'date' },
        ],
        required: true,
      },
      { key: 'delay_value', label: __('Amount', textDomain), component: 'input', placeholder: '5', componentProps: { type: 'number', min: 1 } },
      {
        key: 'delay_period',
        label: __('Period', textDomain),
        component: 'select',
        options: [
          { label: __('Seconds', textDomain), value: 'seconds' },
          { label: __('Minutes', textDomain), value: 'minute' },
          { label: __('Hours', textDomain), value: 'hour' },
          { label: __('Days', textDomain), value: 'day' },
          { label: __('Weeks', textDomain), value: 'week' },
          { label: __('Months', textDomain), value: 'month' },
        ],
      },
      { key: 'date_value', label: __('Date', textDomain), component: 'input', componentProps: { type: 'date' } },
      { key: 'time_value', label: __('Time', textDomain), component: 'input', componentProps: { type: 'time' } },
    ],
    {
      context: [],
      preview: (data) => {
        const amount = String(data.delay_value || '');
        const period = String(data.delay_period || __('minutes', textDomain));
        return `${__('Delay', textDomain)} · ${amount ? `${amount} ${period}` : period}`;
      },
      parseData: (data) => normalizeActionData(data, {
        title: __('Delay', textDomain),
        description: '',
        action: 'time_delay',
        delay_type: 'period',
        delay_value: '',
        delay_period: 'minute',
        date_value: '',
        time_value: '',
        settings: {},
      }),
      serializeData: (data) => normalizeActionData(data, {
        title: __('Delay', textDomain),
        description: '',
        action: 'time_delay',
        delay_type: 'period',
        delay_value: '',
        delay_period: 'minute',
        date_value: '',
        time_value: '',
        settings: {},
      }),
      validate: (data) =>
        requiredFieldValidation(
          [
            { key: 'delay_type', label: __('Delay type', textDomain), component: 'select', required: true },
          ],
          data
        ),
    }
  ),
  createBaseActionDefinition(
    'condition',
    __('Condition', textDomain),
    __('Split the flow into true and false branches.', textDomain),
    'git-branch',
    [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('Condition title', textDomain), required: true },
      {
        key: 'condition',
        label: __('Condition type', textDomain),
        component: 'select',
        required: true,
        options: [
          { label: __('User role', textDomain), value: 'user_role' },
          { label: __('Order status', textDomain), value: 'order_status' },
          { label: __('Cart total', textDomain), value: 'cart_total' },
          { label: __('Items in cart', textDomain), value: 'items_in_cart' },
          { label: __('Field value', textDomain), value: 'field_value' },
          { label: __('Meta value', textDomain), value: 'meta_value' },
          { label: __('Post type', textDomain), value: 'post_type' },
          { label: __('Post status', textDomain), value: 'post_status' },
        ],
      },
      {
        key: 'condition_type',
        label: __('Operator', textDomain),
        component: 'select',
        options: [
          { label: __('Is', textDomain), value: 'is' },
          { label: __('Is not', textDomain), value: 'is_not' },
          { label: __('Contains', textDomain), value: 'contains' },
          { label: __('Does not contain', textDomain), value: 'not_contain' },
          { label: __('Starts with', textDomain), value: 'start_with' },
          { label: __('Ends with', textDomain), value: 'finish_with' },
          { label: __('Greater than', textDomain), value: 'bigger_than' },
          { label: __('Less than', textDomain), value: 'less_than' },
          { label: __('Empty', textDomain), value: 'empty' },
          { label: __('Not empty', textDomain), value: 'not_empty' },
        ],
      },
      { key: 'field_id', label: __('Field ID', textDomain), component: 'input', placeholder: __('Field ID', textDomain) },
      { key: 'meta_key', label: __('Meta key', textDomain), component: 'input', placeholder: __('Meta key', textDomain) },
      { key: 'value_text', label: __('Value', textDomain), component: 'textarea', rows: 4, placeholder: __('Condition value', textDomain) },
      { key: 'type_text', label: __('Type label', textDomain), component: 'input', placeholder: __('Optional type label', textDomain) },
    ],
    {
      context: [],
      preview: (data) => {
        const condition = String(data.condition || __('condition', textDomain));
        const operator = String(data.condition_type || '');
        return operator ? `${__('Condition', textDomain)} · ${condition} / ${operator}` : `${__('Condition', textDomain)} · ${condition}`;
      },
      parseData: (data) => ({
        ...normalizeActionData(data, {
          title: __('Condition', textDomain),
          description: '',
          action: 'condition',
          condition: '',
          condition_type: '',
          field_id: '',
          meta_key: '',
          value_text: '',
          type_text: '',
          settings: {},
        }),
      }),
      serializeData: (data) => ({
        ...normalizeActionData(data, {
          title: __('Condition', textDomain),
          description: '',
          action: 'condition',
          condition: '',
          condition_type: '',
          field_id: '',
          meta_key: '',
          value_text: '',
          type_text: '',
          settings: {},
        }),
      }),
      validate: (data) =>
        requiredFieldValidation(
          [
            { key: 'condition', label: __('Condition type', textDomain), component: 'select', required: true },
          ],
          data
        ),
    }
  ),
  createBaseActionDefinition(
    'stop_funnel',
    __('Stop funnel', textDomain),
    __('End the workflow at this point.', textDomain),
    'ban',
    [],
    {
      context: [],
      preview: () => __('Stops the flow', textDomain),
      parseData: (data) => normalizeActionData(data, {
        title: __('Stop funnel', textDomain),
        description: '',
        action: 'stop_funnel',
        settings: {},
      }),
      serializeData: (data) => normalizeActionData(data, {
        title: __('Stop funnel', textDomain),
        description: '',
        action: 'stop_funnel',
        settings: {},
      }),
    }
  ),
  createBaseActionDefinition(
    'snippet_php',
    __('Snippet PHP', textDomain),
    __('Run a PHP snippet during the workflow.', textDomain),
    'code',
    [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('Snippet PHP', textDomain), required: true },
      { key: 'snippet_php', label: __('PHP snippet', textDomain), component: 'textarea', rows: 12, placeholder: '<?php\n', required: true },
    ],
    {
      context: [],
      preview: (data) => buildPreview(__('Snippet PHP', textDomain), data, __('Custom PHP code', textDomain)),
      parseData: (data) => normalizeActionData(data, {
        title: __('Snippet PHP', textDomain),
        description: '',
        action: 'snippet_php',
        snippet_php: '',
        settings: {},
      }),
      serializeData: (data) => normalizeActionData(data, {
        title: __('Snippet PHP', textDomain),
        description: '',
        action: 'snippet_php',
        snippet_php: '',
        settings: {},
      }),
      validate: (data) =>
        requiredFieldValidation(
          [{ key: 'snippet_php', label: __('PHP snippet', textDomain), component: 'textarea', required: true }],
          data
        ),
    }
  ),
  createBaseActionDefinition(
    'dynamic_placeholder',
    __('Dynamic placeholder', textDomain),
    __('Create a reusable variable from workflow data.', textDomain),
    'variable',
    [
      { key: 'title', label: __('Title', textDomain), component: 'input', placeholder: __('Dynamic placeholder', textDomain), required: true },
      { key: 'dynamic_placeholder_text', label: __('Variable name', textDomain), component: 'input', placeholder: __('variable_name', textDomain), required: true },
      { key: 'dynamic_placeholder_value', label: __('Variable value', textDomain), component: 'textarea', rows: 6, placeholder: __('{{ value }}', textDomain), required: true },
    ],
    {
      context: [],
      preview: (data) => buildPreview(__('Placeholder', textDomain), data, typeof data.dynamic_placeholder_text === 'string' ? data.dynamic_placeholder_text : ''),
      parseData: (data) => normalizeActionData(data, {
        title: __('Dynamic placeholder', textDomain),
        description: '',
        action: 'dynamic_placeholder',
        dynamic_placeholder_text: '',
        dynamic_placeholder_value: '',
        settings: {},
      }),
      serializeData: (data) => normalizeActionData(data, {
        title: __('Dynamic placeholder', textDomain),
        description: '',
        action: 'dynamic_placeholder',
        dynamic_placeholder_text: '',
        dynamic_placeholder_value: '',
        settings: {},
      }),
      validate: (data) =>
        requiredFieldValidation(
          [
            { key: 'dynamic_placeholder_text', label: __('Variable name', textDomain), component: 'input', required: true },
            { key: 'dynamic_placeholder_value', label: __('Variable value', textDomain), component: 'textarea', required: true },
          ],
          data
        ),
    }
  ),
];

let activeActionCatalog: WorkflowRegistryItem[] = ACTION_FALLBACKS.map((item) => cloneActionDefinition(item));

function normalizeActionRecord(raw: BackendActionRecord): WorkflowRegistryItem | null {
  const actionId = String(raw.action || raw.id || raw.slug || raw.key || '').trim();

  if (!actionId) {
    return null;
  }

  const fallback = ACTION_FALLBACKS.find((item) => item.id === actionId);
  const schema = normalizeSchema(raw.schema || raw.fields || raw.settings_schema);
  const contexts = normalizeContexts(raw.context);
  const preview = typeof raw.preview === 'function'
    ? (raw.preview as WorkflowRegistryItem['preview'])
    : fallback?.preview;

  return {
    id: actionId,
    label: String(raw.title || raw.label || fallback?.label || actionId),
    description: String(raw.description || fallback?.description || ''),
    icon: String(raw.icon || fallback?.icon || actionId),
    iconSvg: String(raw.icon_svg || raw.iconSvg || fallback?.iconSvg || ''),
    context: contexts,
    contexts,
    category: String(raw.category || fallback?.category || ''),
    schema: mergeSchema(schema, fallback?.schema || []),
    settingsComponent: String(raw.settings_component || raw.settingsComponent || fallback?.settingsComponent || ''),
    parseData: fallback?.parseData,
    serializeData: fallback?.serializeData,
    preview,
    validate: fallback?.validate,
    requireSettings: Boolean(raw.require_settings ?? raw.requireSettings ?? fallback?.requireSettings),
    enabled: raw.enabled === undefined ? fallback?.enabled : Boolean(raw.enabled),
  };
}

export function normalizeActionCatalog(rawActions: Array<Record<string, unknown>> = []): WorkflowRegistryItem[] {
  const merged = new Map<string, WorkflowRegistryItem>();

  for (const fallback of ACTION_FALLBACKS) {
    merged.set(fallback.id, cloneActionDefinition(fallback));
  }

  for (const raw of rawActions) {
    const normalized = normalizeActionRecord(raw);
    if (!normalized) {
      continue;
    }

    const existing = merged.get(normalized.id);
    merged.set(normalized.id, {
      ...existing,
      ...normalized,
      schema: normalized.schema.length ? normalized.schema : existing?.schema || [],
      contexts: normalized.contexts.length ? normalized.contexts : existing?.contexts || [],
      context: normalized.contexts.length ? normalized.contexts : existing?.context || [],
      parseData: normalized.parseData || existing?.parseData,
      serializeData: normalized.serializeData || existing?.serializeData,
      preview: normalized.preview || existing?.preview,
      validate: normalized.validate || existing?.validate,
    });
  }

  return Array.from(merged.values()).map((item) => cloneActionDefinition(item));
}

export function setActionCatalog(rawActions: Array<Record<string, unknown>> = []) {
  activeActionCatalog = normalizeActionCatalog(rawActions);
}

export function getActionCatalog(): WorkflowRegistryItem[] {
  return activeActionCatalog.map((item) => cloneActionDefinition(item));
}

export const ACTION_REGISTRY: WorkflowRegistryItem[] = ACTION_FALLBACKS;

export function getActionDefinition(actionId: string): WorkflowRegistryItem | undefined {
  return activeActionCatalog.find((item) => item.id === actionId)
    || ACTION_FALLBACKS.find((item) => item.id === actionId);
}

export function getActionsForContext(context: string): WorkflowRegistryItem[] {
  const safeContext = String(context || '').trim();

  if (!safeContext) {
    return getActionCatalog();
  }

  return activeActionCatalog.filter((item) => {
    const contexts = Array.isArray(item.context)
      ? item.context.map((value) => String(value))
      : typeof item.context === 'string'
        ? [item.context]
        : [];

    return contexts.length === 0 || contexts.includes(safeContext);
  });
}

export function getActionRegistryPreview(node: WorkflowNode): string {
  const actionId = String(node.data?.action || '');
  const definition = actionId ? getActionDefinition(actionId) : undefined;

  if (definition?.preview) {
    return definition.preview(node.data || {});
  }

  if (isConditionAction(node.data)) {
    return `${__('Condition', textDomain)} · ${String(node.data.condition || '')}`;
  }

  if (isDelayAction(node.data)) {
    const value = String(node.data.delay_value || '');
    const period = String(node.data.delay_period || '');
    return value ? `${__('Delay', textDomain)} · ${value} ${period}` : __('Delay', textDomain);
  }

  if (isStopAction(node.data)) {
    return __('Stops the flow', textDomain);
  }

  if (isSnippetAction(node.data)) {
    return __('Custom PHP code', textDomain);
  }

  if (isPlaceholderAction(node.data)) {
    return String(node.data.dynamic_placeholder_text || __('Dynamic placeholder', textDomain));
  }

  return String(node.data?.description || node.data?.message || definition?.description || __('Action', textDomain));
}
