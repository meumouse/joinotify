import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
  getActionCatalog,
  getActionDefinition,
  getActionsForContext,
  setActionCatalog,
} from '../registries/actionRegistry';
import { TRIGGER_CONTEXTS, getTriggerContextById } from '../registries/triggerContexts';
import {
  getTriggerCatalog,
  getTriggerContextsCatalog,
  getTriggerDefinition,
  getTriggersForContext,
  setTriggerCatalog,
} from '../registries/triggerRegistry';
import {
  createWorkflowFileFromParts,
  normalizeWorkflowFile,
  parseWorkflowFile,
  parseWorkflowFromJson,
} from '../parsers/workflowParser';
import {
  serializeWorkflowFile,
  serializeWorkflowToJson,
} from '../serializers/workflowSerializer';
import { createWorkflowApiClient } from '../services/workflowApi';
import type {
  BuilderBootstrap,
  BuilderStep,
  ExportedWorkflowFile,
  WorkflowBranchKey,
  WorkflowContextDefinition,
  WorkflowNode,
  WorkflowPlaceholderGroup,
  WorkflowRegistryItem,
} from '../types/workflowBuilder';
import {
  cloneSerializable,
  cloneWorkflowNode,
  createActionNode,
  createConditionNode,
  createEmptyBranches,
  createTriggerNode,
  ensureBranchesOnNode,
  findWorkflowNodeLocation,
  getBranchCollection,
  insertWorkflowNodeAfter,
  insertWorkflowNodeIntoConditionBranch,
  isConditionNode,
  isDelayNode,
  isPlaceholderNode,
  isSnippetNode,
  isStopNode,
  moveWorkflowNode,
  removeWorkflowNode,
  replaceWorkflowNodeData,
} from '../utils/workflowTree';
import { createWorkflowNodeId } from '../utils/workflowIds';

function normalizePlaceholderEntry(placeholder: string, details: unknown): WorkflowPlaceholderGroup | null {
  if (!placeholder || !details || typeof details !== 'object') {
    return null;
  }

  const source = details as Record<string, unknown>;
  const items = Array.isArray(source.items)
    ? source.items
    : [
        {
          placeholder,
          description: typeof source.description === 'string' ? source.description : '',
          group: typeof source.group === 'string' ? source.group : String(source.category || placeholder).trim(),
          category: typeof source.category === 'string' ? source.category : '',
          triggers: Array.isArray(source.triggers) ? source.triggers.map((item) => String(item)) : [],
          replacement: source.replacement && typeof source.replacement === 'object' ? cloneSerializable(source.replacement) : {},
          ...source,
        },
      ];

  return {
    id: String(source.group || source.category || placeholder).trim() || placeholder,
    label: String(source.group_label || source.label || source.category || source.group || placeholder),
    description: typeof source.description === 'string' ? source.description : '',
    items: items.map((item) => ({
      placeholder: String((item as Record<string, unknown>).placeholder || placeholder),
      description: String((item as Record<string, unknown>).description || ''),
      category: String((item as Record<string, unknown>).category || source.category || ''),
      group: String((item as Record<string, unknown>).group || source.group || ''),
      triggers: Array.isArray((item as Record<string, unknown>).triggers)
        ? ((item as Record<string, unknown>).triggers as unknown[]).map((trigger) => String(trigger))
        : [],
      replacement: (item as Record<string, unknown>).replacement && typeof (item as Record<string, unknown>).replacement === 'object'
        ? cloneSerializable((item as Record<string, unknown>).replacement)
        : {},
    })),
  };
}

function normalizePlaceholdersCatalog(raw: unknown): WorkflowPlaceholderGroup[] {
  if (!raw) {
    return [];
  }

  if (Array.isArray(raw)) {
    const itemGroups = raw
      .map((entry) => {
        if (!entry || typeof entry !== 'object') {
          return null;
        }

        const source = entry as Record<string, unknown>;
        const placeholder = String(source.placeholder || source.key || source.id || '');

        if (!placeholder) {
          return null;
        }

        return {
          id: String(source.group || source.category || 'general'),
          label: String(source.group_label || source.label || source.category || 'General'),
          description: typeof source.description === 'string' ? source.description : '',
          items: [
            {
              placeholder,
              description: typeof source.description === 'string' ? source.description : '',
              category: typeof source.category === 'string' ? source.category : '',
              group: typeof source.group === 'string' ? source.group : '',
              triggers: Array.isArray(source.triggers) ? source.triggers.map((item) => String(item)) : [],
              replacement: source.replacement && typeof source.replacement === 'object' ? cloneSerializable(source.replacement) : {},
            },
          ],
        } satisfies WorkflowPlaceholderGroup;
      })
      .filter(Boolean) as WorkflowPlaceholderGroup[];

    return itemGroups;
  }

  if (typeof raw === 'object') {
    const source = raw as Record<string, unknown>;
    const groups: WorkflowPlaceholderGroup[] = [];

    for (const [key, value] of Object.entries(source)) {
      if (Array.isArray(value)) {
        const items = value
          .map((entry) => {
            if (!entry || typeof entry !== 'object') {
              return null;
            }

            const item = entry as Record<string, unknown>;
            const placeholder = String(item.placeholder || item.key || item.id || key || '');

            if (!placeholder) {
              return null;
            }

            return {
              placeholder,
              description: String(item.description || ''),
              category: String(item.category || key || ''),
              group: String(item.group || key || ''),
              triggers: Array.isArray(item.triggers) ? item.triggers.map((trigger) => String(trigger)) : [],
              replacement: item.replacement && typeof item.replacement === 'object' ? cloneSerializable(item.replacement) : {},
            };
          })
          .filter(Boolean);

        if (items.length) {
          groups.push({
            id: key,
            label: String(key).replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase()),
            description: '',
            items: items as WorkflowPlaceholderGroup['items'],
          });
        }

        continue;
      }

      if (value && typeof value === 'object') {
        const maybeGroup = normalizePlaceholderEntry(key, value);
        if (maybeGroup) {
          groups.push(maybeGroup);
        }
      }
    }

    if (groups.length) {
      return groups;
    }

    const flatItems = Object.entries(source)
      .map(([placeholder, details]) => {
        if (!details || typeof details !== 'object') {
          return null;
        }

        const item = details as Record<string, unknown>;
        return {
          placeholder,
          description: String(item.description || ''),
          category: String(item.category || ''),
          group: String(item.group || ''),
          triggers: Array.isArray(item.triggers) ? item.triggers.map((trigger) => String(trigger)) : [],
          replacement: item.replacement && typeof item.replacement === 'object' ? cloneSerializable(item.replacement) : {},
        };
      })
      .filter(Boolean);

    if (flatItems.length) {
      return [
        {
          id: 'general',
          label: 'General',
          description: '',
          items: flatItems as WorkflowPlaceholderGroup['items'],
        },
      ];
    }
  }

  return [];
}

function resolveDefaultContext(catalog: WorkflowContextDefinition[]): string {
  return catalog[0]?.id || TRIGGER_CONTEXTS[0]?.id || 'woocommerce';
}

export const useWorkflowBuilderStore = defineStore('joinotifyWorkflowBuilder', () => {
  const bootstrap = ref<BuilderBootstrap>({});
  const api = ref<ReturnType<typeof createWorkflowApiClient> | null>(null);
  const postId = ref(0);
  const file = ref<ExportedWorkflowFile>(createWorkflowFileFromParts());
  const baseline = ref('');
  const step = ref<BuilderStep>('start');
  const activeContext = ref('');
  const selectedTrigger = ref('');
  const selectedNodeId = ref('');
  const editingNodeId = ref('');
  const drawerOpen = ref(false);
  const drawerMode = ref<'settings' | 'context' | 'menu'>('settings');
  const loading = ref({
    bootstrap: true,
    workflow: false,
    templates: false,
    import: false,
    export: false,
    save: false,
    test: false,
    create: false,
    actions: false,
    status: false,
  });
  const errors = ref<string[]>([]);
  const warnings = ref<string[]>([]);
  const lastExportJson = ref('');
  const templateCatalog = ref<Record<string, unknown>[]>([]);
  const actionsCatalog = ref<WorkflowRegistryItem[]>([]);
  const actionsLoaded = ref(false);
  const placeholderCatalog = ref<WorkflowPlaceholderGroup[]>([]);
  const triggerCatalog = ref<Record<string, WorkflowRegistryItem[]>>(getTriggerCatalog());
  const triggerContextsCache = ref<WorkflowContextDefinition[]>(getTriggerContextsCatalog());

  const workflowContent = computed(() => file.value.workflow_content || []);

  const triggerNode = computed(() => workflowContent.value.find((node) => node.type === 'trigger') || null);

  const selectedNode = computed(() => {
    if (!selectedNodeId.value) {
      return null;
    }

    return findWorkflowNodeLocation(workflowContent.value, selectedNodeId.value)?.node || null;
  });

  const selectedNodeDefinition = computed(() => {
    const node = selectedNode.value;

    if (!node) {
      return undefined;
    }

    if (node.type === 'trigger') {
      return getTriggerDefinition(String(node.data.context || ''), String(node.data.trigger || ''));
    }

    return getActionDefinition(String(node.data.action || ''));
  });

  const selectedTriggerDefinition = computed(() => {
    if (!activeContext.value || !selectedTrigger.value) {
      return undefined;
    }

    return getTriggerDefinition(activeContext.value, selectedTrigger.value);
  });

  const selectedActionDefinition = computed(() => {
    const node = selectedNode.value;

    if (!node || node.type !== 'action') {
      return undefined;
    }

    return getActionDefinition(String(node.data.action || ''));
  });

  const dirty = computed(() => serializeWorkflowToJson(file.value) !== baseline.value);
  const hasErrors = computed(() => errors.value.length > 0);
  const canContinue = computed(() => !!activeContext.value && !!selectedTrigger.value);
  const triggerOptions = computed(() => getTriggersForContext(activeContext.value || ''));
  const triggerContexts = computed(() => triggerContextsCache.value.length ? triggerContextsCache.value : TRIGGER_CONTEXTS);

  function setApiFromBootstrap(value: BuilderBootstrap) {
    bootstrap.value = cloneSerializable(value || {});
    api.value = createWorkflowApiClient(bootstrap.value);

    const workflowState = (value?.workflow as Record<string, unknown> | undefined) || {};
    postId.value = Number(workflowState.post_id || 0) || 0;

    const rawTriggers = value?.triggers && typeof value.triggers === 'object' ? (value.triggers as Record<string, Record<string, unknown>[]>) : {};
    const rawContexts = Array.isArray(value?.trigger_contexts) ? (value.trigger_contexts as Record<string, unknown>[]) : [];

    setTriggerCatalog(rawTriggers, rawContexts);
    triggerCatalog.value = getTriggerCatalog();
    triggerContextsCache.value = getTriggerContextsCatalog();
    placeholderCatalog.value = normalizePlaceholdersCatalog(value?.placeholders);
  }

  function markBaseline() {
    baseline.value = serializeWorkflowToJson(file.value);
  }

  function syncSelectionFromFile() {
    const currentTrigger = triggerNode.value;

    activeContext.value = file.value.post.category || (currentTrigger && typeof currentTrigger.data.context === 'string' ? currentTrigger.data.context : '') || resolveDefaultContext(triggerContexts.value);
    selectedTrigger.value = currentTrigger && typeof currentTrigger.data.trigger === 'string' ? currentTrigger.data.trigger : '';
    selectedNodeId.value = selectedNodeId.value && findWorkflowNodeLocation(workflowContent.value, selectedNodeId.value)
      ? selectedNodeId.value
      : currentTrigger?.id || workflowContent.value[0]?.id || '';
    editingNodeId.value = selectedNodeId.value;
  }

  function applyWorkflowFile(value: ExportedWorkflowFile, nextPostId = postId.value, forceCanvas = false) {
    const normalized = normalizeWorkflowFile(value);
    file.value = normalized;

    if (Number(nextPostId) > 0) {
      postId.value = Number(nextPostId);
    }

    syncSelectionFromFile();
    step.value = forceCanvas ? 'canvas' : (workflowContent.value.length ? 'canvas' : 'trigger');
    drawerOpen.value = false;
    drawerMode.value = 'settings';
    errors.value = [];
    warnings.value = [];
    markBaseline();
  }

  function hydrateFromBootstrap(value: BuilderBootstrap) {
    const previousStep = step.value;
    setApiFromBootstrap(value);

    const candidate = value?.workflow_file || value?.workflow || value;
    const parsed = parseWorkflowFile(candidate);

    if (parsed.ok && parsed.file) {
      applyWorkflowFile(parsed.file, postId.value);
      warnings.value = parsed.warnings;
      errors.value = [];
    } else {
      file.value = createWorkflowFileFromParts({
        plugin_version: String(value?.version || '1.0.0'),
        title: value?.title ? String(value.title) : 'My automation',
        category: resolveDefaultContext(triggerContexts.value),
      });

      activeContext.value = file.value.post.category || resolveDefaultContext(triggerContexts.value);
      selectedTrigger.value = '';
      selectedNodeId.value = '';
      editingNodeId.value = '';
      errors.value = parsed.errors;
      warnings.value = parsed.warnings;
      markBaseline();
    }

    step.value = previousStep || 'start';
  }

  async function loadCanvasActionsFromServer() {
    if (actionsLoaded.value) {
      return { ok: true, actions: actionsCatalog.value };
    }

    if (loading.value.actions) {
      return { ok: true, actions: actionsCatalog.value };
    }

    loading.value.actions = true;

    try {
      const response = api.value ? await api.value.loadActions() : null;
      const rawActions = Array.isArray(response?.actions) ? (response.actions as Record<string, unknown>[]) : [];

      if (rawActions.length) {
        setActionCatalog(rawActions);
        actionsCatalog.value = getActionCatalog();
      } else {
        actionsCatalog.value = getActionCatalog();
      }

      actionsLoaded.value = true;

      return response || { ok: true, actions: actionsCatalog.value };
    } catch (error) {
      actionsCatalog.value = getActionCatalog();
      errors.value = [error instanceof Error ? error.message : 'Could not load actions.'];
      return { ok: false, error };
    } finally {
      loading.value.actions = false;
    }
  }

  async function loadWorkflowFromServer(nextPostId = postId.value) {
    const resolvedPostId = Number(nextPostId || 0) || 0;

    if (resolvedPostId <= 0) {
      return { ok: false };
    }

    if (loading.value.workflow) {
      return { ok: true };
    }

    loading.value.workflow = true;

    try {
      const response = api.value ? await api.value.loadWorkflow(resolvedPostId) : null;

      if (response && typeof response === 'object') {
        const workflowContent = Array.isArray(response.workflow_content)
          ? response.workflow_content
          : Array.isArray(response.content)
            ? response.content
            : Array.isArray(response.workflow?.content)
              ? response.workflow.content
              : [];
        const workflowPost = response.post && typeof response.post === 'object'
          ? response.post
          : response.workflow && typeof response.workflow === 'object'
            ? response.workflow
            : {};

        const workflowFile = createWorkflowFileFromParts({
          plugin_version: String(bootstrap.value.version || '1.0.0'),
          post: {
            type: 'joinotify-workflow',
            title: String(
              response.workflow_title
                || workflowPost.title
                || bootstrap.value.title
                || 'My automation'
            ),
            date: String(response.created_at || workflowPost.date || new Date().toISOString()),
            status: String(response.workflow_status || workflowPost.status || 'draft'),
            modified: String(response.updated_at || workflowPost.modified || new Date().toISOString()),
            category: String(
              workflowContent?.[0]?.data?.context
                || workflowContent?.[0]?.data?.category
                || workflowPost.category
                || ''
            ),
          },
          workflow_content: workflowContent,
        });

        applyWorkflowFile(workflowFile, resolvedPostId, true);
        errors.value = [];
        warnings.value = [];
      }

      return response || { ok: true };
    } catch (error) {
      errors.value = [error instanceof Error ? error.message : 'Could not load workflow.'];
      return { ok: false, error };
    } finally {
      loading.value.workflow = false;
    }
  }

  function createEmptyWorkflowFile(title = 'My automation') {
    const context = resolveDefaultContext(triggerContexts.value);
    file.value = createWorkflowFileFromParts({
      title,
      category: context,
      status: 'draft',
      workflow_content: [
        createTriggerNode({
          title,
          description: '',
          trigger: '',
          context,
          settings: {},
        }),
      ],
    });

    activeContext.value = context;
    selectedTrigger.value = '';
    selectedNodeId.value = file.value.workflow_content[0]?.id || '';
    editingNodeId.value = selectedNodeId.value;
    step.value = 'trigger';
    errors.value = [];
    warnings.value = [];
    markBaseline();

    return file.value;
  }

  function loadWorkflowFile(value: ExportedWorkflowFile, nextPostId = 0) {
    applyWorkflowFile(value, nextPostId);
  }

  async function createWorkflowFromScratch(title = file.value.post.title || 'My automation') {
    loading.value.create = true;

    try {
      const response = api.value ? await api.value.createWorkflow({ mode: 'scratch', title }) : null;

      if (response?.workflow_file) {
        postId.value = Number(response?.workflow?.post_id || 0) || 0;
        applyWorkflowFile(response.workflow_file, postId.value);
        step.value = 'trigger';
        return response;
      }

      createEmptyWorkflowFile(title);
      return { ok: true, workflow_file: file.value };
    } finally {
      loading.value.create = false;
    }
  }

  async function createWorkflowFromTemplate(templateFile: string, title = '') {
    loading.value.create = true;

    try {
      const response = api.value ? await api.value.createWorkflow({ mode: 'template', template_file: templateFile, title }) : null;

      if (response?.workflow_file) {
        applyWorkflowFile(response.workflow_file, response?.workflow?.post_id || 0);
        step.value = 'canvas';
        return response;
      }

      return { ok: false };
    } finally {
      loading.value.create = false;
    }
  }

  async function loadBootstrapFromServer(nextPostId = postId.value) {
    loading.value.bootstrap = true;

    try {
      const response = api.value ? await api.value.loadBootstrap(nextPostId) : null;

      if (response && typeof response === 'object') {
        hydrateFromBootstrap(response);
      }

      return response;
    } finally {
      loading.value.bootstrap = false;
    }
  }

  async function loadTemplatesFromServer(force = false) {
    if (!force && Array.isArray(templateCatalog.value) && templateCatalog.value.length > 0) {
      return { templates: templateCatalog.value };
    }

    loading.value.templates = true;

    try {
      const response = api.value ? await api.value.loadTemplates() : null;

      if (response && Array.isArray(response.templates)) {
        templateCatalog.value = cloneSerializable(response.templates);
      }

      return response;
    } finally {
      loading.value.templates = false;
    }
  }

  function setWorkflowTitle(title: string) {
    file.value.post.title = title;

    const trigger = triggerNode.value;
    if (trigger) {
      trigger.data = {
        ...trigger.data,
        title,
      };
    }
  }

  function setWorkflowStatus(status: string) {
    file.value.post.status = status;
  }

  function markWorkflowSaved() {
    baseline.value = serializeWorkflowToJson(file.value);
  }

  async function updateWorkflowStatus(status: string) {
    if (loading.value.status) {
      return { ok: false };
    }

    if (postId.value <= 0) {
      return { ok: false, error: new Error('Workflow not saved yet.') };
    }

    loading.value.status = true;

    try {
      const response = api.value
        ? await api.value.updateWorkflowStatus({
            post_id: postId.value,
            status,
          })
        : null;

      if (response?.status === 'success' || response?.workflow_status) {
        setWorkflowStatus(status);
        markWorkflowSaved();
        return response;
      }

      return { ok: false };
    } finally {
      loading.value.status = false;
    }
  }

  function setWorkflowCategory(category: string) {
    file.value.post.category = category;
    activeContext.value = category;
    selectedTrigger.value = '';

    const trigger = triggerNode.value;
    if (trigger) {
      trigger.data = {
        ...trigger.data,
        context: category,
        trigger: '',
        description: '',
      };
    }
  }

  function selectTriggerContext(context: string) {
    activeContext.value = context;
    file.value.post.category = context;
    selectedTrigger.value = '';

    const trigger = triggerNode.value;
    if (trigger) {
      trigger.data = {
        ...trigger.data,
        context,
        trigger: '',
        description: '',
      };
    }
  }

  function selectTrigger(triggerId: string) {
    selectedTrigger.value = triggerId;

    const trigger = triggerNode.value;
    const definition = triggerId ? getTriggersForContext(activeContext.value || '').find((item) => item.id === triggerId) : undefined;

    if (trigger) {
      trigger.data = {
        ...trigger.data,
        trigger: triggerId,
        title: definition?.label || trigger.data.title || file.value.post.title,
        description: definition?.description || '',
        context: activeContext.value,
      };
    }
  }

  function updateTriggerNode(patch: Record<string, unknown>) {
    const trigger = triggerNode.value;

    if (!trigger) {
      return;
    }

    trigger.data = {
      ...trigger.data,
      ...cloneSerializable(patch),
    };

    if (typeof patch.context === 'string') {
      setWorkflowCategory(patch.context);
    }

    if (typeof patch.trigger === 'string') {
      selectedTrigger.value = patch.trigger;
    }

    if (typeof patch.title === 'string') {
      setWorkflowTitle(patch.title);
    }
  }

  function updateNodeData(nodeId: string, patch: Record<string, unknown>) {
    if (!nodeId) {
      return;
    }

    if (!replaceWorkflowNodeData(workflowContent.value, nodeId, patch)) {
      return;
    }

    const node = findWorkflowNodeLocation(workflowContent.value, nodeId)?.node;
    if (!node) {
      return;
    }

    if (node.type === 'trigger') {
      if (typeof patch.context === 'string') {
        setWorkflowCategory(patch.context);
      }

      if (typeof patch.trigger === 'string') {
        selectedTrigger.value = patch.trigger;
      }

      if (typeof patch.title === 'string') {
        file.value.post.title = patch.title;
      }
    }
  }

  function addActionNode(
    actionId = 'send_whatsapp_message_text',
    afterNodeId = selectedNodeId.value,
    branchKey?: WorkflowBranchKey
  ) {
    const definition = getActionDefinition(actionId);
    const node = actionId === 'condition'
      ? createConditionNode({
          title: definition?.label || 'Condition',
          description: '',
          action: 'condition',
          condition: '',
          condition_type: '',
          field_id: '',
          meta_key: '',
          value_text: '',
          type_text: '',
          settings: {},
        }, definition)
      : createActionNode(actionId, {
          title: definition?.label || 'Action',
          description: '',
          action: actionId,
          message: '',
          sender: '',
          receiver: '',
          settings: {},
        }, definition);

    if (branchKey) {
      const inserted = insertWorkflowNodeIntoConditionBranch(workflowContent.value, afterNodeId, branchKey, node, afterNodeId);

      if (inserted) {
        selectedNodeId.value = inserted.id;
        editingNodeId.value = inserted.id;
        drawerOpen.value = true;
        drawerMode.value = 'settings';
        return inserted;
      }
    }

    const inserted = afterNodeId
      ? insertWorkflowNodeAfter(workflowContent.value, afterNodeId, node)
      : insertWorkflowNodeAfter(workflowContent.value, triggerNode.value?.id || '', node) || node;

    selectedNodeId.value = inserted?.id || node.id;
    editingNodeId.value = selectedNodeId.value;
    drawerOpen.value = true;
    drawerMode.value = 'settings';

    return inserted || node;
  }

  function addNodeBelow(nodeId: string, actionId: string, branchKey?: WorkflowBranchKey) {
    return addActionNode(actionId, nodeId, branchKey);
  }

  function addNodeToBranch(conditionId: string, branchKey: WorkflowBranchKey, actionId: string) {
    return addActionNode(actionId, conditionId, branchKey);
  }

  function removeNode(nodeId: string) {
    if (!nodeId || triggerNode.value?.id === nodeId) {
      return;
    }

    if (!removeWorkflowNode(workflowContent.value, nodeId)) {
      return;
    }

    const fallback = workflowContent.value.find((node) => node.id !== nodeId) || null;
    selectedNodeId.value = fallback?.id || triggerNode.value?.id || '';
    editingNodeId.value = selectedNodeId.value;
  }

  function duplicateNode(nodeId: string) {
    const location = findWorkflowNodeLocation(workflowContent.value, nodeId);

    if (!location) {
      return null;
    }

    const clone = cloneWorkflowNode(location.node);
    clone.id = createWorkflowNodeId(clone.type);

    if (location.parent && location.branchKey && location.parent.data.action === 'condition' && location.parent.branches) {
      const branch = getBranchCollection(location.parent)[location.branchKey];
      branch.splice(location.index + 1, 0, clone);
      ensureBranchesOnNode(location.parent);
    } else {
      location.container.splice(location.index + 1, 0, clone);
    }

    selectedNodeId.value = clone.id;
    editingNodeId.value = clone.id;
    return clone;
  }

  function moveNode(nodeId: string, direction: 'up' | 'down') {
    return moveWorkflowNode(workflowContent.value, nodeId, direction);
  }

  function openNodeSettings(nodeId: string, mode: 'settings' | 'context' | 'menu' = 'settings') {
    editingNodeId.value = nodeId;
    selectedNodeId.value = nodeId;
    drawerMode.value = mode;
    drawerOpen.value = true;
  }

  function closeNodeSettings() {
    drawerOpen.value = false;
    editingNodeId.value = '';
  }

  function importWorkflowFromJson(json: string) {
    const parsed = parseWorkflowFromJson(json);

    if (!parsed.ok || !parsed.file) {
      errors.value = parsed.errors;
      warnings.value = parsed.warnings;
      return { ok: false, errors: parsed.errors, warnings: parsed.warnings };
    }

    applyWorkflowFile(parsed.file);
    warnings.value = parsed.warnings;
    errors.value = [];
    return parsed;
  }

  async function importWorkflowFromServer(json: string) {
    loading.value.import = true;
    try {
      return importWorkflowFromJson(json);
    } finally {
      loading.value.import = false;
    }
  }

  function exportWorkflowToJson() {
    lastExportJson.value = serializeWorkflowToJson(file.value);
    return lastExportJson.value;
  }

  function serializeWorkflow() {
    return serializeWorkflowFile(file.value);
  }

  function parseWorkflow(input: unknown) {
    return parseWorkflowFile(input);
  }

  async function runWorkflowTest() {
    loading.value.test = true;

    try {
      await Promise.resolve();
      return { ok: true, message: 'Workflow test queued.' };
    } finally {
      loading.value.test = false;
    }
  }

  async function saveWorkflow() {
    loading.value.save = true;

    try {
      const payload = serializeWorkflowFile(file.value);

      if (api.value && postId.value <= 0) {
        const created = await api.value.createWorkflow({ mode: 'scratch', title: file.value.post.title });
        if (created?.workflow?.post_id) {
          postId.value = Number(created.workflow.post_id) || 0;
        }
      }

      const response = api.value ? await api.value.saveWorkflow({ post_id: postId.value, workflow_file: payload }) : null;

      if (response?.workflow_file) {
        applyWorkflowFile(response.workflow_file, response?.workflow?.post_id || postId.value);
        return response;
      }

      markBaseline();
      return { ok: true, workflow_file: payload };
    } finally {
      loading.value.save = false;
    }
  }

  const state = {
    bootstrap,
    postId,
    file,
    step,
    activeContext,
    selectedTrigger,
    selectedNodeId,
    editingNodeId,
    drawerOpen,
    drawerMode,
    loading,
    templateCatalog,
    actionsCatalog,
    actionsLoaded,
    placeholderCatalog,
    triggerCatalog,
    triggerContexts,
    errors,
    warnings,
    dirty,
    hasErrors,
    canContinue,
    workflowContent,
    triggerNode,
    selectedNode,
    selectedNodeDefinition,
    selectedTriggerDefinition,
    selectedActionDefinition,
    triggerOptions,
    bootstrapData: bootstrap,
    setApiFromBootstrap,
    hydrateFromBootstrap,
    createEmptyWorkflowFile,
    createWorkflowFromScratch,
    createWorkflowFromTemplate,
    loadWorkflowFile,
    loadBootstrapFromServer,
    loadWorkflowFromServer,
    loadTemplatesFromServer,
    loadCanvasActionsFromServer,
    setWorkflowTitle,
    setWorkflowStatus,
    updateWorkflowStatus,
    setWorkflowCategory,
    selectTriggerContext,
    selectTrigger,
    updateTriggerNode,
    addActionNode,
    addNodeBelow,
    addNodeToBranch,
    updateNodeData,
    removeNode,
    duplicateNode,
    moveNode,
    openNodeSettings,
    closeNodeSettings,
    importWorkflowFromJson,
    importWorkflowFromServer,
    exportWorkflowToJson,
    serializeWorkflow,
    parseWorkflow,
    runWorkflowTest,
    saveWorkflow,
    getActionsForContext,
    getTriggersForContext,
    getTriggerDefinition,
    getActionDefinition,
    resolveDefaultContext,
  };

  return state;
});
