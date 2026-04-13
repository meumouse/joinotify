import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { ACTION_REGISTRY, getActionDefinition } from '../registries/actionRegistry';
import { TRIGGER_CONTEXTS } from '../registries/triggerContexts';
import { getTriggerDefinition, getTriggersForContext } from '../registries/triggerRegistry';
import { createWorkflowFileFromParts, normalizeWorkflowFile, parseWorkflowFile, parseWorkflowFromJson } from '../parsers/workflowParser';
import { serializeWorkflowFile, serializeWorkflowToJson } from '../serializers/workflowSerializer';
import { createWorkflowApiClient } from '../services/workflowApi';
import type { BuilderBootstrap, BuilderStep, ExportedWorkflowFile, WorkflowNode } from '../types/workflowBuilder';
import { createWorkflowNodeId } from '../utils/workflowIds';

function cloneValue<T>(value: T): T {
  return JSON.parse(JSON.stringify(value ?? null));
}

function findNodePath(
  nodes: WorkflowNode[],
  targetId: string,
  trail: WorkflowNode[] = []
): { node: WorkflowNode; parent: WorkflowNode | null; index: number; trail: WorkflowNode[] } | null {
  for (let index = 0; index < nodes.length; index += 1) {
    const node = nodes[index];

    if (node.id === targetId) {
      return { node, parent: trail[trail.length - 1] || null, index, trail };
    }

    if (Array.isArray(node.children) && node.children.length > 0) {
      const result = findNodePath(node.children, targetId, [...trail, node]);
      if (result) {
        return result;
      }
    }
  }

  return null;
}

function cloneNodeWithNewIds(node: WorkflowNode): WorkflowNode {
  return {
    ...cloneValue(node),
    id: createWorkflowNodeId(node.type),
    children: (node.children || []).map((child) => cloneNodeWithNewIds(child)),
  };
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
    import: false,
    export: false,
    save: false,
    test: false,
    create: false,
  });
  const errors = ref<string[]>([]);
  const warnings = ref<string[]>([]);
  const lastExportJson = ref('');

  const workflowContent = computed(() => file.value.workflow_content || []);
  const triggerNode = computed(() => workflowContent.value.find((node) => node.type === 'trigger') || null);
  const selectedNode = computed(() => (selectedNodeId.value ? findNodePath(workflowContent.value, selectedNodeId.value)?.node || null : null));
  const selectedTriggerDefinition = computed(() => {
    if (!activeContext.value || !selectedTrigger.value) {
      return undefined;
    }

    return getTriggerDefinition(activeContext.value, selectedTrigger.value);
  });
  const selectedActionDefinition = computed(() => {
    const node = selectedNode.value;
    const actionId = node && node.type === 'action' && typeof node.data.action === 'string' ? node.data.action : '';

    return actionId ? getActionDefinition(actionId) : undefined;
  });
  const dirty = computed(() => serializeWorkflowToJson(file.value) !== baseline.value);
  const hasErrors = computed(() => errors.value.length > 0);
  const canContinue = computed(() => !!activeContext.value && !!selectedTrigger.value);
  const triggerOptions = computed(() => getTriggersForContext(activeContext.value || ''));

  function setApiFromBootstrap(value: BuilderBootstrap) {
    bootstrap.value = cloneValue(value || {});
    api.value = createWorkflowApiClient(bootstrap.value);
    postId.value = Number((value?.workflow as Record<string, unknown> | undefined)?.post_id || 0) || 0;
  }

  function markBaseline() {
    baseline.value = serializeWorkflowToJson(file.value);
  }

  function hydrateFromBootstrap(value: BuilderBootstrap) {
    setApiFromBootstrap(value);

    const candidate = value?.workflow_file || value?.workflow || value;
    const parsed = parseWorkflowFile(candidate);

    if (parsed.ok && parsed.file) {
      file.value = normalizeWorkflowFile(parsed.file);
      errors.value = [];
      warnings.value = parsed.warnings;
    } else {
      file.value = createWorkflowFileFromParts({
        plugin_version: String(value?.version || '1.0.0'),
        title: value?.title || 'My automation',
      });
      errors.value = parsed.errors;
      warnings.value = parsed.warnings;
    }

    selectedNodeId.value = triggerNode.value?.id || workflowContent.value[0]?.id || '';
    editingNodeId.value = selectedNodeId.value;
    activeContext.value = file.value.post.category || (triggerNode.value && typeof triggerNode.value.data.context === 'string' ? triggerNode.value.data.context : '');
    selectedTrigger.value = triggerNode.value && typeof triggerNode.value.data.trigger === 'string' ? triggerNode.value.data.trigger : '';
    step.value = workflowContent.value.length ? 'canvas' : 'start';
    drawerOpen.value = false;
    drawerMode.value = 'settings';
    markBaseline();
  }

  function createEmptyWorkflowFile(title = 'My automation') {
    const context = TRIGGER_CONTEXTS[0]?.id || 'woocommerce';
    file.value = createWorkflowFileFromParts({
      title,
      category: context,
      status: 'draft',
      workflow_content: [
        {
          id: createWorkflowNodeId('trigger'),
          type: 'trigger',
          data: {
            title,
            description: '',
            trigger: '',
            context,
          },
          children: [],
        },
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
    const parsed = normalizeWorkflowFile(value);
    file.value = parsed;
    if (Number(nextPostId) > 0) {
      postId.value = Number(nextPostId);
    }
    activeContext.value = parsed.post.category || '';
    selectedTrigger.value = triggerNode.value && typeof triggerNode.value.data.trigger === 'string' ? triggerNode.value.data.trigger : '';
    selectedNodeId.value = triggerNode.value?.id || parsed.workflow_content[0]?.id || '';
    editingNodeId.value = selectedNodeId.value;
    step.value = parsed.workflow_content.length ? 'canvas' : 'trigger';
    markBaseline();
  }

  async function createWorkflowFromScratch(title = file.value.post.title || 'My automation') {
    loading.value.create = true;

    try {
      const response = api.value ? await api.value.createWorkflow({ mode: 'scratch', title }) : null;

      if (response?.workflow_file) {
        postId.value = Number(response?.workflow?.post_id || 0) || 0;
        createEmptyWorkflowFile(title);
        file.value.post.title = title;
        file.value.post.status = 'draft';
        file.value.post.category = TRIGGER_CONTEXTS[0]?.id || 'woocommerce';
        step.value = 'trigger';
        return response;
      }

      createEmptyWorkflowFile(title);
      return { ok: true, workflow_file: file.value };
    } finally {
      loading.value.create = false;
    }
  }

  async function createWorkflowFromTemplate(templateFile, title = '') {
    loading.value.create = true;

    try {
      const response = api.value ? await api.value.createWorkflow({ mode: 'template', template_file: templateFile, title }) : null;

      if (response?.workflow_file) {
        loadWorkflowFile(response.workflow_file, response?.workflow?.post_id || 0);
        step.value = 'canvas';
        return response;
      }

      return { ok: false };
    } finally {
      loading.value.create = false;
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
    const definition = triggerId ? getTriggerDefinition(activeContext.value, triggerId) : undefined;

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
      ...patch,
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
    const path = findNodePath(workflowContent.value, nodeId);

    if (!path) {
      return;
    }

    path.node.data = {
      ...path.node.data,
      ...patch,
    };

    if (path.node.type === 'trigger') {
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

  function addActionNode(actionId = 'send_whatsapp_message_text', afterNodeId = selectedNodeId.value) {
    const definition = getActionDefinition(actionId);
    const node: WorkflowNode = {
      id: createWorkflowNodeId('action'),
      type: 'action',
      data: definition?.parseData
        ? definition.parseData({
            title: definition.label,
            description: '',
            action: actionId,
            message: '',
            sender: '',
            receiver: '',
          })
        : {
            title: definition?.label || 'Action',
            description: '',
            action: actionId,
            message: '',
            sender: '',
            receiver: '',
          },
      children: [],
    };

    if (!afterNodeId) {
      file.value.workflow_content.push(node);
      selectedNodeId.value = node.id;
      editingNodeId.value = node.id;
      return node;
    }

    const path = findNodePath(workflowContent.value, afterNodeId);
    if (!path) {
      file.value.workflow_content.push(node);
    } else if (path.parent) {
      path.parent.children.splice(path.index + 1, 0, node);
    } else {
      workflowContent.value.splice(path.index + 1, 0, node);
    }

    selectedNodeId.value = node.id;
    editingNodeId.value = node.id;
    drawerOpen.value = true;
    drawerMode.value = 'settings';

    return node;
  }

  function removeNode(nodeId: string) {
    const path = findNodePath(workflowContent.value, nodeId);
    if (!path) {
      return;
    }

    if (path.parent) {
      path.parent.children.splice(path.index, 1);
    } else {
      workflowContent.value.splice(path.index, 1);
    }

    selectedNodeId.value = workflowContent.value[0]?.id || '';
    editingNodeId.value = selectedNodeId.value;
  }

  function duplicateNode(nodeId: string) {
    const path = findNodePath(workflowContent.value, nodeId);
    if (!path) {
      return null;
    }

    const clone = cloneNodeWithNewIds(path.node);

    if (path.parent) {
      path.parent.children.splice(path.index + 1, 0, clone);
    } else {
      workflowContent.value.splice(path.index + 1, 0, clone);
    }

    selectedNodeId.value = clone.id;
    editingNodeId.value = clone.id;
    return clone;
  }

  function moveNode(nodeId: string, direction: 'up' | 'down') {
    const path = findNodePath(workflowContent.value, nodeId);
    if (!path) {
      return;
    }

    const siblings = path.parent ? path.parent.children : workflowContent.value;
    const targetIndex = direction === 'up' ? path.index - 1 : path.index + 1;

    if (targetIndex < 0 || targetIndex >= siblings.length) {
      return;
    }

    const [item] = siblings.splice(path.index, 1);
    siblings.splice(targetIndex, 0, item);
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

    loadWorkflowFile(parsed.file);
    errors.value = [];
    warnings.value = parsed.warnings;
    return parsed;
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
        loadWorkflowFile(response.workflow_file, response?.workflow?.post_id || postId.value);
        return response;
      }

      markBaseline();
      return { ok: true, workflow_file: payload };
    } finally {
      loading.value.save = false;
    }
  }

  async function importWorkflowFromServer(json: string) {
    loading.value.import = true;
    try {
      return importWorkflowFromJson(json);
    } finally {
      loading.value.import = false;
    }
  }

  return {
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
    errors,
    warnings,
    dirty,
    hasErrors,
    canContinue,
    workflowContent,
    triggerNode,
    selectedNode,
    selectedTriggerDefinition,
    selectedActionDefinition,
    triggerOptions,
    bootstrapData: bootstrap,
    actionsCatalog: ACTION_REGISTRY,
    triggerContexts: TRIGGER_CONTEXTS,
    setApiFromBootstrap,
    hydrateFromBootstrap,
    createEmptyWorkflowFile,
    createWorkflowFromScratch,
    createWorkflowFromTemplate,
    loadWorkflowFile,
    setWorkflowTitle,
    setWorkflowStatus,
    setWorkflowCategory,
    selectTriggerContext,
    selectTrigger,
    updateTriggerNode,
    addActionNode,
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
  };
});
