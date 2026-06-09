import { defineStore } from 'pinia';
import { computed, nextTick, ref, watch } from 'vue';
import {
  getActionCatalog,
  getActionDefinition,
  getActionsForContext,
  registerBuilderAction,
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
import { getTriggerSettingsSchema } from '../utils/triggerSettings';
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
import { createDebugLogger } from '../utils/debug';
import { __, textDomain } from '../utils/i18n';
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
  insertWorkflowNodeIntoConditionBranch,
  insertWorkflowNodeAtEnd,
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
          label: String(source.group_label || source.label || source.category || __('General', textDomain)),
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
          label: __('General', textDomain),
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

function toFiniteNumber(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string') {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }

  return null;
}

export const useWorkflowBuilderStore = defineStore('joinotifyWorkflowBuilder', () => {
  const bootstrap = ref<BuilderBootstrap>({});
  const api = ref<ReturnType<typeof createWorkflowApiClient> | null>(null);
  const debugLogger = createDebugLogger('Builder', () => Boolean(bootstrap.value?.debug_mode));
  const postId = ref(0);
  const file = ref<ExportedWorkflowFile>(createWorkflowFileFromParts());
  const baseline = ref('');
  const undoStack = ref<string[]>([]);
  const redoStack = ref<string[]>([]);
  const isTimeTraveling = ref(false);
  const HISTORY_LIMIT = 100;
  let lastHistorySnapshot = '';
  let historyDebounceTimer: ReturnType<typeof setTimeout> | null = null;
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
    actionDetails: false,
    status: false,
  });
  const errors = ref<string[]>([]);
  const warnings = ref<string[]>([]);
  const lastExportJson = ref('');
  const templateCatalog = ref<Record<string, unknown>[]>([]);
  const actionsCatalog = ref<WorkflowRegistryItem[]>([]);
  const actionCategories = ref<Array<Record<string, unknown>>>([]);
  const actionsLoaded = ref(false);
  const actionsCatalogContext = ref('');
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

  function getNodeById(nodeId: string) {
    if (!nodeId) {
      return null;
    }

    return findWorkflowNodeLocation(workflowContent.value, nodeId)?.node || null;
  }

  function getNodeCanvasPosition(node: WorkflowNode | null | undefined) {
    const candidate = node?.data?.canvas_position;
    const source = candidate && typeof candidate === 'object' ? (candidate as Record<string, unknown>) : null;
    const x = source ? toFiniteNumber(source.x) : null;
    const y = source ? toFiniteNumber(source.y) : null;

    if (x !== null && y !== null) {
      return {
        x,
        y,
      };
    }

    return null;
  }

  function resolveFloatingNodePosition(referenceId: string) {
    const referenceNode = getNodeById(referenceId) || triggerNode.value || selectedNode.value;
    const referencePosition = getNodeCanvasPosition(referenceNode) || { x: 320, y: 80 };
    const floatingCount = workflowContent.value.filter((node) => node.data?.connection_mode === 'floating').length;

    return {
      x: referencePosition.x + 280,
      y: referencePosition.y + 140 + (floatingCount * 36),
    };
  }

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
  const canUndo = computed(() => undoStack.value.length > 0);
  const canRedo = computed(() => redoStack.value.length > 0);
  const hasErrors = computed(() => errors.value.length > 0);
  const canContinue = computed(() => !!activeContext.value && !!selectedTrigger.value);
  const triggerOptions = computed(() => getTriggersForContext(activeContext.value || ''));
  const triggerContexts = computed(() => triggerContextsCache.value.length ? triggerContextsCache.value : TRIGGER_CONTEXTS);

  function setApiFromBootstrap(value: BuilderBootstrap) {
    bootstrap.value = cloneSerializable(value || {});
    api.value = createWorkflowApiClient(bootstrap.value);
    debugLogger.log('bootstrap:api-ready', {
      debug_mode: Boolean(bootstrap.value?.debug_mode),
      post_id: Number(bootstrap.value?.workflow?.post_id || 0) || 0,
    });

    const workflowState = (value?.workflow as Record<string, unknown> | undefined) || {};
    postId.value = Number(workflowState.post_id || 0) || 0;

    const rawTriggers = value?.triggers && typeof value.triggers === 'object' ? (value.triggers as Record<string, Record<string, unknown>[]>) : {};
    const rawContexts = Array.isArray(value?.trigger_contexts) ? (value.trigger_contexts as Record<string, unknown>[]) : [];

    setTriggerCatalog(rawTriggers, rawContexts);
    triggerCatalog.value = getTriggerCatalog();
    triggerContextsCache.value = getTriggerContextsCatalog();
    placeholderCatalog.value = normalizePlaceholdersCatalog(value?.placeholders);
    actionsLoaded.value = false;
    actionsCatalogContext.value = '';
  }

  function markBaseline() {
    baseline.value = serializeWorkflowToJson(file.value);
  }

  function resetHistory() {
    if (historyDebounceTimer) {
      clearTimeout(historyDebounceTimer);
      historyDebounceTimer = null;
    }

    undoStack.value = [];
    redoStack.value = [];
    isTimeTraveling.value = false;
    lastHistorySnapshot = serializeWorkflowToJson(file.value);
  }

  function commitHistorySnapshot() {
    if (isTimeTraveling.value) {
      return;
    }

    const current = serializeWorkflowToJson(file.value);

    if (current === lastHistorySnapshot) {
      return;
    }

    undoStack.value.push(lastHistorySnapshot);

    if (undoStack.value.length > HISTORY_LIMIT) {
      undoStack.value.shift();
    }

    redoStack.value = [];
    lastHistorySnapshot = current;
  }

  function applyHistorySnapshot(json: string) {
    const parsed = parseWorkflowFromJson(json);

    if (!parsed.ok || !parsed.file) {
      return false;
    }

    isTimeTraveling.value = true;
    file.value = normalizeWorkflowFile(parsed.file);

    if (selectedNodeId.value && !findWorkflowNodeLocation(workflowContent.value, selectedNodeId.value)) {
      selectedNodeId.value = triggerNode.value?.id || workflowContent.value[0]?.id || '';
      editingNodeId.value = selectedNodeId.value;
    }

    // The serialized-file watcher flushes before this nextTick callback, so the
    // guard stays active while the restore propagates and no snapshot is recorded.
    void nextTick(() => {
      isTimeTraveling.value = false;
    });

    return true;
  }

  function undo() {
    if (!undoStack.value.length) {
      return false;
    }

    const previous = undoStack.value.pop();

    if (typeof previous !== 'string') {
      return false;
    }

    redoStack.value.push(lastHistorySnapshot);
    lastHistorySnapshot = previous;

    debugLogger.log('history:undo', {
      undo_remaining: undoStack.value.length,
      redo_available: redoStack.value.length,
    });

    return applyHistorySnapshot(previous);
  }

  function redo() {
    if (!redoStack.value.length) {
      return false;
    }

    const next = redoStack.value.pop();

    if (typeof next !== 'string') {
      return false;
    }

    undoStack.value.push(lastHistorySnapshot);
    lastHistorySnapshot = next;

    debugLogger.log('history:redo', {
      undo_available: undoStack.value.length,
      redo_remaining: redoStack.value.length,
    });

    return applyHistorySnapshot(next);
  }

  watch(
    () => serializeWorkflowToJson(file.value),
    () => {
      if (isTimeTraveling.value) {
        return;
      }

      if (historyDebounceTimer) {
        clearTimeout(historyDebounceTimer);
      }

      // Debounce so a burst of changes (e.g. a node drag) collapses into one entry.
      historyDebounceTimer = setTimeout(() => {
        historyDebounceTimer = null;
        commitHistorySnapshot();
      }, 350);
    }
  );

  function syncSelectionFromFile() {
    const currentTrigger = triggerNode.value;

    activeContext.value = file.value.post.category || (currentTrigger && typeof currentTrigger.data.context === 'string' ? currentTrigger.data.context : '') || resolveDefaultContext(triggerContexts.value);
    selectedTrigger.value = currentTrigger && typeof currentTrigger.data.trigger === 'string' ? currentTrigger.data.trigger : '';
    selectedNodeId.value = selectedNodeId.value && findWorkflowNodeLocation(workflowContent.value, selectedNodeId.value)
      ? selectedNodeId.value
      : currentTrigger?.id || workflowContent.value[0]?.id || '';
    editingNodeId.value = selectedNodeId.value;
  }

  // A pre-fix builder bug (commit 86f8a44) could persist a trigger node with an empty
  // `trigger` slug while keeping its context and settings. Such nodes lose their
  // definition lookup, so the settings modal shows "no additional settings" and the
  // trigger can no longer be configured. Recover the slug deterministically from the
  // saved settings keys (each trigger's settings schema is unique within a context),
  // falling back to a title match, so legacy workflows become editable again.
  function recoverTriggerNodeSlug(): boolean {
    const node = triggerNode.value;

    if (!node || node.type !== 'trigger') {
      return false;
    }

    const context = String(node.data?.context || '');

    if (!context || String(node.data?.trigger || '')) {
      return false;
    }

    const candidates = getTriggersForContext(context);

    if (!candidates.length) {
      return false;
    }

    const settings = node.data?.settings && typeof node.data.settings === 'object'
      ? (node.data.settings as Record<string, unknown>)
      : {};
    const settingsKeys = Object.keys(settings);

    let match: WorkflowRegistryItem | undefined;

    // Deterministic: the saved settings keys match exactly one trigger's schema.
    if (settingsKeys.length) {
      match = candidates.find((candidate) => {
        const schemaKeys = getTriggerSettingsSchema(candidate).map((field) => field.key);
        return schemaKeys.length > 0 && settingsKeys.every((key) => schemaKeys.includes(key));
      });
    }

    // Fallback: the node title still matches a trigger label in this context.
    if (!match) {
      const title = String(node.data?.title || '').trim().toLowerCase();

      if (title) {
        match = candidates.find((candidate) => String(candidate.label || '').trim().toLowerCase() === title);
      }
    }

    if (!match) {
      return false;
    }

    node.data = {
      ...node.data,
      trigger: match.id,
    };
    debugLogger.log('trigger:slug-recovered', {
      node_id: node.id,
      context,
      trigger: match.id,
    });

    return true;
  }

  function applyWorkflowFile(value: ExportedWorkflowFile, nextPostId = postId.value, forceCanvas = false) {
    const normalized = normalizeWorkflowFile(value);
    file.value = normalized;
    const recovered = recoverTriggerNodeSlug();

    if (Number(nextPostId) > 0) {
      postId.value = Number(nextPostId);
    }

    syncSelectionFromFile();
    step.value = forceCanvas ? 'canvas' : (triggerNode.value ? 'canvas' : 'trigger');
    drawerOpen.value = false;
    drawerMode.value = 'settings';
    errors.value = [];
    warnings.value = [];
    markBaseline();
    resetHistory();

    // A recovered trigger slug is an in-memory repair only; flag it as unsaved so
    // saving persists the fix and rebuilds the runtime trigger-hook index (a workflow
    // with an empty trigger never fires until re-saved).
    if (recovered) {
      baseline.value = '';
    }
    void loadCanvasActionsFromServer(activeContext.value);
  }

  function hydrateFromBootstrap(value: BuilderBootstrap) {
    const previousStep = step.value;
    loading.value.bootstrap = true;
    try {
      setApiFromBootstrap(value);
      debugLogger.log('bootstrap:hydrate-start', {
        step: previousStep,
      });

      const candidate = value?.workflow_file || value?.workflow || value;
      const parsed = parseWorkflowFile(candidate);

      if (parsed.ok && parsed.file) {
        applyWorkflowFile(parsed.file, postId.value);
        warnings.value = parsed.warnings;
        errors.value = [];
      } else {
        file.value = createWorkflowFileFromParts({
          plugin_version: String(value?.version || '1.0.0'),
          title: value?.title ? String(value.title) : __('My automation', textDomain),
          category: resolveDefaultContext(triggerContexts.value),
        });

        activeContext.value = file.value.post.category || resolveDefaultContext(triggerContexts.value);
        selectedTrigger.value = '';
        selectedNodeId.value = '';
        editingNodeId.value = '';
        errors.value = parsed.errors;
        warnings.value = parsed.warnings;
        markBaseline();
        void loadCanvasActionsFromServer(activeContext.value);
        debugLogger.log('bootstrap:hydrate-empty', {
          errors: parsed.errors,
        });
      }

      step.value = previousStep || 'start';
      debugLogger.log('bootstrap:hydrate-complete', {
        step: step.value,
        node_count: workflowContent.value.length,
      });
    } finally {
      loading.value.bootstrap = false;
    }
  }

  async function loadCanvasActionsFromServer(nextContext = activeContext.value) {
    const context = String(nextContext || '').trim();

    if (actionsLoaded.value && actionsCatalogContext.value === context) {
      return { ok: true, actions: actionsCatalog.value };
    }

    if (loading.value.actions) {
      return { ok: true, actions: actionsCatalog.value };
    }

    loading.value.actions = true;
    debugLogger.log('actions:load-start');

    try {
      const response = api.value ? await api.value.loadActions(context) : null;
      const rawActions = Array.isArray(response?.actions) ? (response.actions as Record<string, unknown>[]) : [];
      const rawCategories = Array.isArray(response?.categories) ? (response.categories as Array<Record<string, unknown>>) : [];

      if (rawCategories.length) {
        actionCategories.value = rawCategories;
      }

      if (rawActions.length) {
        setActionCatalog(rawActions);
        actionsCatalog.value = getActionCatalog();
      } else {
        actionsCatalog.value = getActionCatalog();
      }

      actionsLoaded.value = true;
      actionsCatalogContext.value = context;
      debugLogger.log('actions:load-complete', {
        count: actionsCatalog.value.length,
        context,
      });

      return response || { ok: true, actions: actionsCatalog.value };
    } catch (error) {
      actionsCatalog.value = getActionCatalog();
      actionsCatalogContext.value = context;
      errors.value = [error instanceof Error ? error.message : __('Could not load actions.', textDomain)];
      debugLogger.log('actions:load-failed', {
        error: error instanceof Error ? error.message : String(error),
        context,
      });
      return { ok: false, error };
    } finally {
      loading.value.actions = false;
    }
  }

  async function loadActionDefinitionFromServer(actionId: string) {
    const normalizedAction = String(actionId || '').trim();

    if (!normalizedAction || !api.value) {
      return { ok: false };
    }

    if (loading.value.actionDetails) {
      return { ok: true };
    }

    loading.value.actionDetails = true;
    debugLogger.log('action:load-definition-start', {
      action_id: normalizedAction,
    });

    try {
      const response = await api.value.loadAction(normalizedAction);
      const actionDefinition = response?.action;

      if (actionDefinition && typeof actionDefinition === 'object') {
        registerBuilderAction(actionDefinition as Record<string, unknown>);
        actionsCatalog.value = getActionCatalog();
      }

      debugLogger.log('action:load-definition-complete', {
        action_id: normalizedAction,
        has_definition: Boolean(actionDefinition),
      });

      return response || { ok: true };
    } catch (error) {
      debugLogger.log('action:load-definition-failed', {
        action_id: normalizedAction,
        error: error instanceof Error ? error.message : String(error),
      });
      return { ok: false, error };
    } finally {
      loading.value.actionDetails = false;
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
    debugLogger.log('workflow:load-start', {
      workflow_id: resolvedPostId,
    });

    try {
      const response = api.value ? await api.value.loadWorkflow(resolvedPostId) : null;

      if (response && typeof response === 'object') {
        const parsed = parseWorkflowFile(response);

        if (parsed.ok && parsed.file) {
          applyWorkflowFile(parsed.file, resolvedPostId);
          errors.value = [];
          warnings.value = parsed.warnings;
        } else {
          errors.value = parsed.errors;
          warnings.value = parsed.warnings;
        }

        debugLogger.log('workflow:load-complete', {
          workflow_id: resolvedPostId,
          node_count: workflowContent.value.length,
        });
      }

      return response || { ok: true };
    } catch (error) {
      errors.value = [error instanceof Error ? error.message : __('Could not load workflow.', textDomain)];
      debugLogger.log('workflow:load-failed', {
        workflow_id: resolvedPostId,
        error: error instanceof Error ? error.message : String(error),
      });
      return { ok: false, error };
    } finally {
      loading.value.workflow = false;
      loading.value.bootstrap = false;
    }
  }

  function createEmptyWorkflowFile(title = __('My automation', textDomain)) {
    const context = resolveDefaultContext(triggerContexts.value);
    debugLogger.log('workflow:create-empty', {
      title,
      context,
    });
    postId.value = 0;
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
    resetHistory();
    void loadCanvasActionsFromServer(activeContext.value);

    return file.value;
  }

  function resetWorkflowSession() {
    debugLogger.log('workflow:reset-session');
    postId.value = 0;
    baseline.value = '';
    file.value = createWorkflowFileFromParts();
    step.value = 'start';
    activeContext.value = '';
    selectedTrigger.value = '';
    selectedNodeId.value = '';
    editingNodeId.value = '';
    drawerOpen.value = false;
    drawerMode.value = 'settings';
    errors.value = [];
    warnings.value = [];
    loading.value.workflow = false;
    loading.value.create = false;
    actionsLoaded.value = false;
    actionsCatalogContext.value = '';
    resetHistory();
  }

  function loadWorkflowFile(value: ExportedWorkflowFile, nextPostId = 0) {
    applyWorkflowFile(value, nextPostId);
  }

  function createWorkflowFromScratch(title = file.value.post.title || __('My automation', textDomain)) {
    debugLogger.log('workflow:create-from-scratch-start', {
      title,
    });

    createEmptyWorkflowFile(title);
    step.value = 'trigger';

    debugLogger.log('workflow:create-from-scratch-complete', {
      title,
      workflow_id: postId.value,
    });

    return { ok: true, workflow_file: file.value };
  }

  async function createWorkflowFromTrigger(
    title = file.value.post.title || __('My automation', textDomain),
    context = activeContext.value,
    trigger = selectedTrigger.value,
    settings: Record<string, unknown> = {}
  ) {
    if (!api.value) {
      return { ok: false };
    }

    loading.value.create = true;
    debugLogger.log('workflow:create-from-trigger-start', {
      title,
      context,
      trigger,
    });

    try {
      const response = await api.value.createWorkflow({
        mode: 'scratch',
        title,
        context,
        trigger,
        settings: cloneSerializable(settings),
      });

      const nextPostId = Number(response?.workflow?.post_id || response?.post_id || 0) || 0;

      if (response?.workflow_file) {
        applyWorkflowFile(response.workflow_file, nextPostId, true);
      } else if (nextPostId > 0) {
        postId.value = nextPostId;
        file.value = createWorkflowFileFromParts({
          title,
          category: context,
          status: 'draft',
          workflow_content: workflowContent.value,
        });
        step.value = 'canvas';
        markBaseline();
      }

      debugLogger.log('workflow:create-from-trigger-complete', {
        workflow_id: nextPostId,
      });

      return response;
    } finally {
      loading.value.create = false;
    }
  }

  async function createWorkflowFromTemplate(templateFile: string, title = '') {
    loading.value.create = true;
    debugLogger.log('workflow:create-from-template-start', {
      template_file: templateFile,
      title,
    });

    try {
      const response = api.value ? await api.value.createWorkflow({ mode: 'template', template_file: templateFile, title }) : null;

      if (response?.workflow_file) {
        applyWorkflowFile(response.workflow_file, response?.workflow?.post_id || 0);
        step.value = 'canvas';
        debugLogger.log('workflow:create-from-template-complete', {
          template_file: templateFile,
          workflow_id: response?.workflow?.post_id || 0,
        });
        return response;
      }

      return { ok: false };
    } finally {
      loading.value.create = false;
    }
  }

  async function generateWorkflowFromAi(payload: Record<string, unknown> = {}) {
    loading.value.create = true;
    debugLogger.log('workflow:generate-ai-start', {
      context: String(payload?.context || ''),
    });

    try {
      const response = api.value
        ? await api.value.generateAiWorkflow({ intent: 'flow', ...payload })
        : null;

      const content = response && Array.isArray((response as Record<string, unknown>).workflow_content)
        ? ((response as Record<string, unknown>).workflow_content as unknown[])
        : null;

      if (response && (response as Record<string, unknown>).status === 'success' && content) {
        const file = createWorkflowFileFromParts({
          title: String((response as Record<string, unknown>).title || __('AI workflow', textDomain)),
          category: String((response as Record<string, unknown>).category || ''),
          status: 'draft',
          workflow_content: content as ExportedWorkflowFile['workflow_content'],
        });

        applyWorkflowFile(file, 0);
        debugLogger.log('workflow:generate-ai-complete', {
          nodes: content.length,
        });

        return { ok: true, response };
      }

      return {
        ok: false,
        message: String((response as Record<string, unknown>)?.message || __('The AI could not generate the workflow.', textDomain)),
      };
    } finally {
      loading.value.create = false;
    }
  }

  async function generateAiSnippet(payload: Record<string, unknown> = {}) {
    debugLogger.log('snippet:generate-ai-start');

    try {
      const response = api.value
        ? await api.value.generateAi({ intent: 'snippet', ...payload })
        : null;

      if (response && (response as Record<string, unknown>).status === 'success') {
        return { ok: true, code: String((response as Record<string, unknown>).code || '') };
      }

      return {
        ok: false,
        message: String((response as Record<string, unknown>)?.message || __('The AI could not generate the snippet.', textDomain)),
      };
    } catch (error) {
      return {
        ok: false,
        message: error instanceof Error ? error.message : __('The AI could not generate the snippet.', textDomain),
      };
    }
  }

  async function loadBootstrapFromServer(nextPostId = postId.value) {
    loading.value.bootstrap = true;
    debugLogger.log('bootstrap:load-start', {
      workflow_id: Number(nextPostId || 0) || 0,
    });

    try {
      const response = api.value ? await api.value.loadBootstrap(nextPostId) : null;

      if (response && typeof response === 'object') {
        hydrateFromBootstrap(response);
      }

      debugLogger.log('bootstrap:load-complete', {
        workflow_id: Number(nextPostId || 0) || 0,
      });
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
    debugLogger.log('templates:load-start', {
      force,
    });

    try {
      const response = api.value ? await api.value.loadTemplates() : null;

      if (response && Array.isArray(response.templates)) {
        templateCatalog.value = cloneSerializable(response.templates);
      }

      debugLogger.log('templates:load-complete', {
        count: Array.isArray(templateCatalog.value) ? templateCatalog.value.length : 0,
      });
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
      return { ok: false, error: new Error(__('Workflow not saved yet.', textDomain)) };
    }

    loading.value.status = true;
    debugLogger.log('workflow:status-update-start', {
      workflow_id: postId.value,
      status,
    });

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
        debugLogger.log('workflow:status-update-complete', {
          workflow_id: postId.value,
          status,
        });
        return response;
      }

      debugLogger.log('workflow:status-update-failed', {
        workflow_id: postId.value,
        status,
      });
      return { ok: false };
    } finally {
      loading.value.status = false;
    }
  }

  function setWorkflowCategory(category: string) {
    file.value.post.category = category;
    activeContext.value = category;
    selectedTrigger.value = '';
    void loadCanvasActionsFromServer(category);

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
    debugLogger.log('trigger:context-selected', {
      context,
    });
    activeContext.value = context;
    file.value.post.category = context;
    selectedTrigger.value = '';
    void loadCanvasActionsFromServer(context);

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
    debugLogger.log('trigger:selected', {
      context: activeContext.value,
      trigger: triggerId,
    });
    selectedTrigger.value = triggerId;

    const definition = triggerId ? getTriggersForContext(activeContext.value || '').find((item) => item.id === triggerId) : undefined;

    let trigger = triggerNode.value;

    if (!trigger) {
      const newNode = createTriggerNode({
        title: definition?.label || file.value.post.title,
        description: definition?.description || '',
        trigger: triggerId,
        context: activeContext.value,
        settings: {},
      });
      file.value.workflow_content = [newNode, ...(file.value.workflow_content || [])];
      selectedNodeId.value = newNode.id;
      editingNodeId.value = newNode.id;
      debugLogger.log('trigger:node-created', { node_id: newNode.id });
      return;
    }

    trigger.data = {
      ...trigger.data,
      trigger: triggerId,
      title: definition?.label || trigger.data.title || file.value.post.title,
      description: definition?.description || '',
      context: activeContext.value,
    };
  }

  function updateTriggerNode(patch: Record<string, unknown>) {
    const trigger = triggerNode.value;

    if (!trigger) {
      return;
    }

    // Capture the previous context before merging: a settings payload carries the
    // context string on every save, so only react to a genuine context switch —
    // otherwise setWorkflowCategory would wipe the trigger/description (see updateNodeData).
    const previousContext = String(trigger.data?.context || '');

    trigger.data = {
      ...trigger.data,
      ...cloneSerializable(patch),
    };

    if (typeof patch.context === 'string' && patch.context !== previousContext) {
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

    debugLogger.log('node:update-requested', {
      node_id: nodeId,
      keys: Object.keys(patch || {}),
    });

    // Capture the previous context before the patch is merged: the settings
    // drawer emits the full node draft on every change (context included), so
    // we must only react to a genuine context switch — otherwise saving any
    // trigger setting would re-run setWorkflowCategory and wipe trigger/description.
    const previousNode = findWorkflowNodeLocation(workflowContent.value, nodeId)?.node;
    const previousContext = previousNode?.type === 'trigger'
      ? String(previousNode.data?.context || '')
      : '';

    if (!replaceWorkflowNodeData(workflowContent.value, nodeId, patch)) {
      return;
    }

    const node = findWorkflowNodeLocation(workflowContent.value, nodeId)?.node;
    if (!node) {
      return;
    }

    if (node.type === 'trigger') {
      if (typeof patch.context === 'string' && patch.context !== previousContext) {
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
    branchKey?: WorkflowBranchKey,
    actionDefinition?: Record<string, unknown> | null
  ) {
    debugLogger.log('node:add-requested', {
      action_id: actionId,
      after_node_id: afterNodeId,
      branch_key: branchKey || '',
    });

    if (actionDefinition && typeof actionDefinition === 'object') {
      registerBuilderAction(actionDefinition);
    }

    const definition = getActionDefinition(actionId);
    const node = actionId === 'condition'
      ? createConditionNode({
          title: definition?.label || __('Condition', textDomain),
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
          title: definition?.label || __('Action', textDomain),
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
        debugLogger.log('node:add-complete', {
          node_id: inserted.id,
          action_id: actionId,
          branch_key: branchKey,
        });
        return inserted;
      }
    }

    const canvasPosition = resolveFloatingNodePosition(afterNodeId || triggerNode.value?.id || '');

    node.data = {
      ...node.data,
      connection_from: null,
      connection_mode: 'floating',
      connection_break_before: null,
      canvas_position: canvasPosition,
    };

    const inserted = insertWorkflowNodeAtEnd(workflowContent.value, node);

    selectedNodeId.value = inserted?.id || node.id;
    editingNodeId.value = selectedNodeId.value;
    debugLogger.log('node:add-complete', {
      node_id: inserted?.id || node.id,
      action_id: actionId,
      branch_key: branchKey || '',
    });

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

    debugLogger.log('node:remove-requested', {
      node_id: nodeId,
    });

    if (!removeWorkflowNode(workflowContent.value, nodeId)) {
      return;
    }

    const fallback = workflowContent.value.find((node) => node.id !== nodeId) || null;
    selectedNodeId.value = fallback?.id || triggerNode.value?.id || '';
    editingNodeId.value = selectedNodeId.value;
    debugLogger.log('node:remove-complete', {
      node_id: nodeId,
      next_selected_node_id: selectedNodeId.value,
    });
  }

  function duplicateNode(nodeId: string) {
    const location = findWorkflowNodeLocation(workflowContent.value, nodeId);

    if (!location) {
      return null;
    }

    debugLogger.log('node:duplicate-requested', {
      node_id: nodeId,
    });

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
    debugLogger.log('node:duplicate-complete', {
      original_node_id: nodeId,
      cloned_node_id: clone.id,
    });
    return clone;
  }

  function moveNode(nodeId: string, direction: 'up' | 'down') {
    debugLogger.log('node:move-requested', {
      node_id: nodeId,
      direction,
    });
    return moveWorkflowNode(workflowContent.value, nodeId, direction);
  }

  function openNodeSettings(nodeId: string, mode: 'settings' | 'context' | 'menu' = 'settings') {
    debugLogger.log('node:open-settings', {
      node_id: nodeId,
      mode,
    });
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
    debugLogger.log('workflow:import-requested', {
      input_length: json?.length || 0,
    });
    const parsed = parseWorkflowFromJson(json);

    if (!parsed.ok || !parsed.file) {
      errors.value = parsed.errors;
      warnings.value = parsed.warnings;
      debugLogger.log('workflow:import-failed', {
        errors: parsed.errors,
      });
      return { ok: false, errors: parsed.errors, warnings: parsed.warnings };
    }

    applyWorkflowFile(parsed.file);
    warnings.value = parsed.warnings;
    errors.value = [];
    debugLogger.log('workflow:import-complete', {
      node_count: workflowContent.value.length,
    });
    return parsed;
  }

  async function importWorkflowFromServer(json: string) {
    loading.value.import = true;
    debugLogger.log('workflow:import-from-server-start');
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
    debugLogger.log('workflow:test-start', {
      workflow_id: postId.value,
    });

    try {
      const response = api.value ? await api.value.runWorkflowTest({ post_id: postId.value }) : null;
      debugLogger.log('workflow:test-complete', {
        workflow_id: postId.value,
      });
      return response || { ok: true, message: __('Workflow test queued.', textDomain) };
    } finally {
      loading.value.test = false;
    }
  }

  async function saveSettings(settings) {
    if (!api.value) {
      throw new Error(__('API client not initialized.', textDomain));
    }

    const response = await api.value.saveSettings({ settings });

    if (response?.settings) {
      bootstrap.value = {
        ...bootstrap.value,
        settings: cloneSerializable(response.settings),
      };
    }

    return response;
  }

  async function saveWorkflow() {
    loading.value.save = true;
    debugLogger.log('workflow:save-start', {
      workflow_id: postId.value,
    });

    try {
      const payload = serializeWorkflowFile(file.value);

      if (api.value && postId.value <= 0) {
        const createPayload: Record<string, unknown> = {
          mode: 'scratch',
          title: file.value.post.title,
        };

        const triggerNode = Array.isArray(payload.workflow_content)
          ? payload.workflow_content.find((item) => item && typeof item === 'object' && String((item as Record<string, unknown>).type || '') === 'trigger')
          : null;
        const triggerNodeData = triggerNode && typeof triggerNode === 'object' && triggerNode.data && typeof triggerNode.data === 'object'
          ? (triggerNode.data as Record<string, unknown>)
          : null;
        const triggerValue = triggerNodeData ? String(triggerNodeData.trigger || '') : '';
        const contextValue = triggerNodeData ? String(triggerNodeData.context || '') : '';

        if (Array.isArray(payload.workflow_content) && payload.workflow_content.length > 0 && (triggerValue || contextValue || payload.workflow_content.length > 1)) {
          createPayload.workflow_content = cloneSerializable(payload.workflow_content);
        } else if (triggerValue || contextValue) {
          createPayload.context = contextValue;
          createPayload.trigger = triggerValue;
        }

        const created = await api.value.createWorkflow(createPayload);
        if (created?.workflow?.post_id) {
          postId.value = Number(created.workflow.post_id) || 0;
        } else if (created?.post_id) {
          postId.value = Number(created.post_id) || 0;
        }
      }

      const response = api.value ? await api.value.saveWorkflow({ post_id: postId.value, workflow_file: payload }) : null;

      if (response?.workflow?.post_id) {
        postId.value = Number(response.workflow.post_id) || postId.value;
      } else if (response?.post_id) {
        postId.value = Number(response.post_id) || postId.value;
      }

      markBaseline();
      debugLogger.log('workflow:save-complete', {
        workflow_id: postId.value,
        mode: 'server',
      });
      return response || { ok: true, workflow_file: payload };
    } finally {
      loading.value.save = false;
    }
  }

  async function saveWorkflowSnapshot(workflowFile: ExportedWorkflowFile) {
    loading.value.save = true;
    debugLogger.log('workflow:save-snapshot-start', {
      workflow_id: postId.value,
    });

    try {
      const payload = serializeWorkflowFile(workflowFile);

      if (!api.value) {
        return { ok: false, error: new Error(__('Workflow API is not available.', textDomain)) };
      }

      if (postId.value <= 0) {
        const createPayload: Record<string, unknown> = {
          mode: 'scratch',
          title: workflowFile.post.title,
        };

        const triggerNode = Array.isArray(payload.workflow_content)
          ? payload.workflow_content.find((item) => item && typeof item === 'object' && String((item as Record<string, unknown>).type || '') === 'trigger')
          : null;
        const triggerNodeData = triggerNode && typeof triggerNode === 'object' && triggerNode.data && typeof triggerNode.data === 'object'
          ? (triggerNode.data as Record<string, unknown>)
          : null;
        const triggerValue = triggerNodeData ? String(triggerNodeData.trigger || '') : '';
        const contextValue = triggerNodeData ? String(triggerNodeData.context || '') : '';

        if (Array.isArray(payload.workflow_content) && payload.workflow_content.length > 0 && (triggerValue || contextValue || payload.workflow_content.length > 1)) {
          createPayload.workflow_content = cloneSerializable(payload.workflow_content);
        } else if (triggerValue || contextValue) {
          createPayload.context = contextValue;
          createPayload.trigger = triggerValue;
        }

        const created = await api.value.createWorkflow(createPayload);
        if (created?.workflow?.post_id) {
          postId.value = Number(created.workflow.post_id) || 0;
        } else if (created?.post_id) {
          postId.value = Number(created.post_id) || 0;
        }

        if (created?.status === 'success' || created?.workflow?.post_id || created?.post_id) {
          markBaseline();
          return created;
        }

        return { ok: false };
      }

      const response = await api.value.saveWorkflow({ post_id: postId.value, workflow_file: payload });

      if (response?.workflow?.post_id) {
        postId.value = Number(response.workflow.post_id) || postId.value;
      } else if (response?.post_id) {
        postId.value = Number(response.post_id) || postId.value;
      }

      markBaseline();
      debugLogger.log('workflow:save-snapshot-complete', {
        workflow_id: postId.value,
      });
      return response || { ok: true, workflow_file: payload };
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
    actionCategories,
    actionsLoaded,
    placeholderCatalog,
    triggerCatalog,
    triggerContexts,
    errors,
    warnings,
    dirty,
    canUndo,
    canRedo,
    hasErrors,
    canContinue,
    workflowContent,
    triggerNode,
    selectedNode,
    selectedNodeDefinition,
    getNodeById,
    selectedTriggerDefinition,
    selectedActionDefinition,
    triggerOptions,
    bootstrapData: bootstrap,
    setApiFromBootstrap,
    hydrateFromBootstrap,
    createEmptyWorkflowFile,
    createWorkflowFromScratch,
    createWorkflowFromTemplate,
    generateWorkflowFromAi,
    generateAiSnippet,
    loadWorkflowFile,
    loadBootstrapFromServer,
    loadWorkflowFromServer,
    loadTemplatesFromServer,
    loadCanvasActionsFromServer,
    loadActionDefinitionFromServer,
    createWorkflowFromTrigger,
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
    saveSettings,
    saveWorkflow,
    saveWorkflowSnapshot,
    undo,
    redo,
    resetHistory,
    resetWorkflowSession,
    getActionsForContext,
    getTriggersForContext,
    getTriggerDefinition,
    getActionDefinition,
    resolveDefaultContext,
  };

  return state;
});
