<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../../components/base/BaseButton.vue';
import ModalDialog from '../../components/modals/ModalDialog.vue';
import BaseInput from '../../components/base/BaseInput.vue';
import PhoneField from '../../components/fields/PhoneField.vue';
import BuilderCanvasView from '../../components/builder/BuilderCanvasView.vue';
import BuilderImportModal from '../../components/builder/BuilderImportModal.vue';
import BuilderNavbar from '../../components/builder/BuilderNavbar.vue';
import BuilderShell from '../../components/builder/BuilderShell.vue';
import BuilderStartView from '../../components/builder/BuilderStartView.vue';
import BuilderTemplateLibraryView from '../../components/builder/BuilderTemplateLibraryView.vue';
import BuilderTriggerSetupView from '../../components/builder/BuilderTriggerSetupView.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { createWorkflowFileFromParts } from '../../parsers/workflowParser';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';
import { createDebugLogger } from '../../utils/debug';
import { cloneValue } from '../../utils/object';

defineOptions({ name: 'BuilderPage' });

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const store = useWorkflowBuilderStore();
const bootstrap = ref(cloneValue(props.bootstrap || {}));
const templateSearch = ref('');
const templateCategory = ref('all');
const importJson = ref('');
const importFileName = ref('');
const importError = ref('');
const importModalOpen = ref(false);
const actionModalOpen = ref(false);
const actionInsertTarget = ref({
  afterNodeId: '',
  branchKey: '',
});
const titleModalOpen = ref(false);
const titleDraft = ref('');
const titleSaving = ref(false);
const triggerContinuing = ref(false);
const testPhoneModalOpen = ref(false);
const testPhoneDraft = ref('');
const testPhoneSaving = ref(false);
const routeWorkflowLoaded = ref(false);
const deleteConfirmOpen = ref(false);
const deleteConfirmBusy = ref(false);
const deleteConfirmNodeId = ref('');
const deleteConfirmNodeLabel = ref('');
const deleteConfirmHasNestedFlow = ref(false);
const toasts = ref([]);
const toastTimers = new Map();

const templates = computed(() => store.templateCatalog || []);
const backUrl = computed(() => bootstrap.value?.links?.back_url || '#');
const docsUrl = computed(() => bootstrap.value?.links?.docs_url || '#');
const debugMode = computed(() => Boolean(bootstrap.value?.debug_mode));
const debugLogger = createDebugLogger('Builder', () => debugMode.value);
const isRunningTest = computed(() => store.loading.test || testPhoneSaving.value);
const savedTestPhoneNumber = computed(() => String(bootstrap.value?.settings?.test_number_phone || '').trim());
const startShellStyle = computed(() => ({
  top: debugMode.value ? '32px !important' : '0',
  height: debugMode.value ? 'calc(100vh - 32px)' : '100vh',
}));
const workflowIdFromUrl = computed(() => {
  if (typeof window === 'undefined') {
    return 0;
  }

  return Number(new URL(window.location.href).searchParams.get('id') || 0) || 0;
});
const availableActions = computed(() => {
  const actions = Array.isArray(store.actionsCatalog) ? store.actionsCatalog : [];
  const context = store.activeContext || '';

  return actions.filter((action) => {
    const contexts = Array.isArray(action.contexts)
      ? action.contexts.map((item) => String(item))
      : Array.isArray(action.context)
        ? action.context.map((item) => String(item))
        : typeof action.context === 'string'
          ? [action.context]
          : [];

    return !context || contexts.length === 0 || contexts.includes(context);
  });
});
const canvasHasTrigger = computed(() => {
  const triggerId = String(store.triggerNode?.data?.trigger || store.selectedTrigger || '').trim();
  return Boolean(triggerId);
});
const canvasHasActions = computed(() => availableActions.value.length > 0);
const canvasHasSenders = computed(() => {
  const senders = Array.isArray(store.bootstrap?.phones?.senders)
    ? store.bootstrap.phones.senders
    : [];

  return senders.some((item) => {
    if (!item || typeof item !== 'object') {
      return false;
    }

    return Boolean(String(item.phone || '').trim());
  });
});
const canvasFlowReady = computed(() => canvasHasTrigger.value && canvasHasActions.value && canvasHasSenders.value);
const actionSidebarOpen = computed(() => Boolean(actionModalOpen.value));
const isSavingTitle = computed(() => titleSaving.value || store.loading.save);
const isUpdatingStatus = computed(() => Boolean(store.loading.status));
const categoryOptions = computed(() => {
  const categories = [...new Set(templates.value.map((template) => template.category).filter(Boolean))];

  return [
    { label: __('All categories', textDomain), value: 'all' },
    ...categories.map((category) => ({ label: category, value: category })),
  ];
});

const filteredTemplates = computed(() => {
  const term = templateSearch.value.trim().toLowerCase();

  return templates.value.filter((template) => {
    const matchesCategory = templateCategory.value === 'all' || template.category === templateCategory.value;
    const searchable = [template.title, template.description, template.integration, template.trigger, template.category]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return matchesCategory && (!term || searchable.includes(term));
  });
});

watch(
  () => props.bootstrap,
  (value) => {
    routeWorkflowLoaded.value = false;
    bootstrap.value = cloneValue(value || {});
    testPhoneDraft.value = String(bootstrap.value?.settings?.test_number_phone || '').trim();
    debugLogger.log('bootstrap:loaded', {
      debug_mode: debugMode.value,
      post_id: workflowIdFromUrl.value,
    });

    if (workflowIdFromUrl.value > 0) {
      store.setApiFromBootstrap(bootstrap.value);
      store.step = 'canvas';
      debugLogger.log('builder:opened-existing-workflow', {
        workflow_id: workflowIdFromUrl.value,
      });
      return;
    }

    store.hydrateFromBootstrap(bootstrap.value);
    debugLogger.log('builder:hydrated-from-bootstrap', {
      step: store.step,
      has_workflow: Boolean(store.workflowContent.length),
    });
  },
  { deep: true, immediate: true }
);

watch(
  () => store.step,
  async (step) => {
    if (step !== 'canvas' || routeWorkflowLoaded.value) {
      return;
    }

    const persistedWorkflowId = workflowIdFromUrl.value > 0
      ? workflowIdFromUrl.value
      : (Number(store.postId || 0) || 0);

    if (persistedWorkflowId > 0) {
      const response = await store.loadBootstrapFromServer(persistedWorkflowId);

      if (response && response.ok === false) {
        return;
      }
    } else {
      await store.loadCanvasActionsFromServer(store.activeContext);
    }

    routeWorkflowLoaded.value = true;
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  toastTimers.forEach((timers) => {
    if (timers.hide) {
      window.clearTimeout(timers.hide);
    }

    if (timers.remove) {
      window.clearTimeout(timers.remove);
    }
  });

  toastTimers.clear();
});

function goStart() {
  debugLogger.log('navigation:go-start');
  store.step = 'start';
}

function goLibrary() {
  debugLogger.log('navigation:go-library');
  store.step = 'library';
}

function goTrigger() {
  debugLogger.log('navigation:go-trigger');
  store.step = 'trigger';
}

function goCanvas() {
  debugLogger.log('navigation:go-canvas');
  store.step = 'canvas';
}

function handleNodeUpdate(payload) {
  const isExplicitPayload = payload && typeof payload === 'object' && payload.nodeId && payload.patch;
  const nodeId = isExplicitPayload
    ? String(payload.nodeId || '')
    : (store.editingNodeId || store.selectedNodeId);
  const patch = isExplicitPayload ? payload.patch : payload;

  if (!nodeId || !patch || typeof patch !== 'object') {
    return;
  }

  store.updateNodeData(nodeId, patch);
}

function openActionSidebar(afterNodeId) {
  const target = afterNodeId && typeof afterNodeId === 'object'
    ? afterNodeId
    : { afterNodeId };
  const targetNodeId = String(target.afterNodeId || store.triggerNode?.id || store.selectedNodeId || '');

  actionInsertTarget.value = {
    afterNodeId: targetNodeId,
    branchKey: String(target.branchKey || ''),
  };
  actionModalOpen.value = Boolean(targetNodeId);

  void store.loadCanvasActionsFromServer(store.activeContext);
}

function goBack() {
  debugLogger.log('navigation:back', {
    url: backUrl.value,
  });
  window.location.href = backUrl.value;
}

function openImportModal() {
  debugLogger.log('modal:open-import');
  importJson.value = '';
  importFileName.value = '';
  importError.value = '';
  importModalOpen.value = true;
}

function closeImportModal() {
  debugLogger.log('modal:close-import');
  importModalOpen.value = false;
}

function openTitleModal() {
  debugLogger.log('modal:open-title');
  titleDraft.value = store.file.post.title || '';
  titleModalOpen.value = true;
}

function closeTitleModal() {
  debugLogger.log('modal:close-title');
  titleModalOpen.value = false;
}

function openRunTestModal() {
  testPhoneDraft.value = savedTestPhoneNumber.value;
  testPhoneModalOpen.value = true;
}

function closeTestPhoneModal() {
  testPhoneModalOpen.value = false;
}

function normalizeToastTone(tone) {
  if (tone === 'danger') {
    return 'error';
  }

  if (tone === 'success' || tone === 'warning' || tone === 'error' || tone === 'info') {
    return tone;
  }

  return 'info';
}

function clearToastTimers(id) {
  const timers = toastTimers.get(id);

  if (!timers) {
    return;
  }

  if (timers.hide) {
    window.clearTimeout(timers.hide);
  }

  if (timers.remove) {
    window.clearTimeout(timers.remove);
  }

  toastTimers.delete(id);
}

function removeToast(id) {
  clearToastTimers(id);
  toasts.value = toasts.value.filter((item) => item.id !== id);
}

function setToastClosing(id, closing) {
  toasts.value = toasts.value.map((item) => (item.id === id ? { ...item, closing } : item));
}

function pushToast(message, tone = 'info', title = __('Joinotify', textDomain)) {
  const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const normalizedTone = normalizeToastTone(tone);

  toasts.value.push({ id, title, message, tone: normalizedTone, closing: false });

  const hideTimer = window.setTimeout(() => {
    setToastClosing(id, true);
  }, 3000);

  const removeTimer = window.setTimeout(() => {
    removeToast(id);
  }, 3500);

  toastTimers.set(id, { hide: hideTimer, remove: removeTimer });
}

function dismissToast(id) {
  clearToastTimers(id);
  setToastClosing(id, true);

  const removeTimer = window.setTimeout(() => {
    toasts.value = toasts.value.filter((item) => item.id !== id);
  }, 180);

  toastTimers.set(id, { hide: null, remove: removeTimer });
}

async function saveTitleModal() {
  const nextTitle = titleDraft.value.trim() || 'New workflow';
  debugLogger.log('workflow:title-save-requested', {
    next_title: nextTitle,
    previous_title: store.file.post.title || '',
  });

  store.setWorkflowTitle(nextTitle);
  pushToast(__('Workflow name updated. Click Save flow to persist changes.', textDomain), 'info', __('Builder', textDomain));
  closeTitleModal();
}

function handleWorkflowStatusChange(nextStatus) {
  const previousStatus = store.file.post.status || 'draft';

  if (previousStatus === nextStatus) {
    return;
  }

  debugLogger.log('workflow:status-change-requested', {
    from: previousStatus,
    to: nextStatus,
  });

  store.setWorkflowStatus(nextStatus);
  pushToast(
    nextStatus === 'publish'
      ? __('Workflow activated locally. Click Save flow to persist changes.', textDomain)
      : __('Workflow deactivated locally. Click Save flow to persist changes.', textDomain),
    'info',
    __('Builder', textDomain)
  );
}

async function startScratch() {
  const title = store.file.post.title || 'New workflow';
  debugLogger.log('start:scratch', { title });
  store.createEmptyWorkflowFile(title);
  goTrigger();
}

async function continueFromTriggerSetup() {
  debugLogger.log('trigger:continue');
  triggerContinuing.value = true;

  try {
    const response = await store.createWorkflowFromTrigger(
      store.file.post.title,
      store.activeContext,
      store.selectedTrigger
    );

    if (!response || response?.ok === false) {
      throw new Error(__('Could not create the workflow.', textDomain));
    }

    syncBuilderUrl(response?.workflow?.post_id || store.postId);
    routeWorkflowLoaded.value = false;
    goCanvas();
  } catch (error) {
    console.error(error);
    debugLogger.log('trigger:continue-failed', {
      error: error instanceof Error ? error.message : String(error),
    });
    window.alert(error instanceof Error ? error.message : __('Could not save the selected trigger.', textDomain));
  } finally {
    triggerContinuing.value = false;
  }
}

async function openTemplateLibrary() {
  debugLogger.log('templates:open-library');
  goLibrary();
  await store.loadTemplatesFromServer();
}

async function openTemplate(template) {
  debugLogger.log('templates:select-template', {
    title: template?.title || '',
    file: template?.file || '',
  });

  if (template.file) {
    await store.createWorkflowFromTemplate(template.file, template.title || '');
  } else if (Array.isArray(template.workflow_content)) {
    store.loadWorkflowFile(
      createWorkflowFileFromParts({
        plugin_version: bootstrap.value.version || '1.0.0',
        post: {
          type: 'joinotify-workflow',
          title: template.title || 'Template',
          date: template.date || new Date().toISOString().slice(0, 19).replace('T', ' '),
          status: 'draft',
          modified: template.modified || new Date().toISOString().slice(0, 19).replace('T', ' '),
          category: template.category || '',
        },
        workflow_content: template.workflow_content,
      })
    );
  }

  syncBuilderUrl(store.postId);
  routeWorkflowLoaded.value = false;
  goCanvas();
}

function handleImportFile(file) {
  debugLogger.log('import:file-selected', {
    file_name: file?.name || 'file.json',
  });
  importFileName.value = file?.name || 'file.json';

  const reader = new FileReader();
  reader.onload = () => {
    importJson.value = String(reader.result || '');
    importError.value = '';
  };
  reader.onerror = () => {
    importError.value = 'Could not read the selected file.';
  };
  reader.readAsText(file);
}

function confirmImport() {
  debugLogger.log('import:confirm', {
    file_name: importFileName.value,
  });
  const result = store.importWorkflowFromJson(importJson.value);

  if (!result.ok) {
    debugLogger.log('import:failed', {
      errors: result.errors,
    });
    importError.value = result.errors?.[0] || 'Invalid file.';
    return;
  }

  importModalOpen.value = false;
  debugLogger.log('import:completed');
  goCanvas();
}

function handleActionOpen(afterNodeId) {
  debugLogger.log('actions:open-picker', {
    after_node_id: typeof afterNodeId === 'object' ? afterNodeId?.afterNodeId || '' : afterNodeId || '',
  });
  openActionSidebar(afterNodeId);
}

function handleActionSelect(action) {
  const actionId = typeof action === 'string'
    ? action
    : String(action?.action || action?.id || action?.slug || '').trim();
  const actionDefinition = typeof action === 'object' && action
    ? action
    : store.getActionDefinition(actionId);

  const targetNodeId = actionInsertTarget.value.afterNodeId || store.triggerNode?.id || store.selectedNodeId || '';
  const branchKey = actionInsertTarget.value.branchKey || undefined;

  debugLogger.log('actions:add-node', {
    action_id: actionId,
    after_node_id: targetNodeId,
    branch_key: branchKey || '',
  });
  const insertedNode = store.addActionNode(actionId, targetNodeId, branchKey, action);
  actionModalOpen.value = false;
  actionInsertTarget.value = {
    afterNodeId: '',
    branchKey: '',
  };
  goCanvas();

  const shouldOpenSettings = Boolean(
    actionDefinition && (
      actionDefinition.hasSettings
      || actionDefinition.requireSettings
      || (Array.isArray(actionDefinition.settingsSchema) && actionDefinition.settingsSchema.length > 0)
    )
  );

  if (shouldOpenSettings && insertedNode?.id) {
    store.openNodeSettings(insertedNode.id);
  }

  void store.loadActionDefinitionFromServer(actionId);
}

function closeActionSidebar() {
  debugLogger.log('actions:close-picker');
  actionModalOpen.value = false;
  actionInsertTarget.value = {
    afterNodeId: '',
    branchKey: '',
  };
}

async function createNewWorkflow() {
  debugLogger.log('workflow:create-new');
  actionModalOpen.value = false;
  importModalOpen.value = false;
  titleModalOpen.value = false;
  testPhoneModalOpen.value = false;
  actionInsertTarget.value = {
    afterNodeId: '',
    branchKey: '',
  };
  routeWorkflowLoaded.value = false;
  store.resetWorkflowSession();
  clearBuilderUrl();
  goStart();
}

async function saveWorkflow() {
  debugLogger.log('workflow:save-requested');
  try {
    const response = await store.saveWorkflow();
    syncBuilderUrl(response?.workflow?.post_id || store.postId);
    pushToast(
      __('Workflow saved successfully.', textDomain),
      'success',
      __('Builder', textDomain)
    );
    debugLogger.log('workflow:save-completed', {
      workflow_id: response?.workflow?.post_id || store.postId,
    });
  } catch (error) {
    pushToast(
      error instanceof Error ? error.message : __('Could not save the workflow.', textDomain),
      'error',
      __('Builder', textDomain)
    );
    debugLogger.log('workflow:save-failed', {
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

function openDeleteConfirm(nodeId) {
  const targetNodeId = String(nodeId || '').trim();

  if (!targetNodeId || targetNodeId === store.triggerNode?.id) {
    return;
  }

  const targetNode = store.getNodeById(targetNodeId);
  const fallbackLabel = String(targetNode?.data?.title || targetNode?.data?.action || __('action', textDomain));
  const hasNestedChildren = Boolean(
    Array.isArray(targetNode?.children) && targetNode.children.length > 0
    || (targetNode?.branches && (
      (Array.isArray(targetNode.branches.action_true) && targetNode.branches.action_true.length > 0)
      || (Array.isArray(targetNode.branches.action_false) && targetNode.branches.action_false.length > 0)
    ))
  );

  deleteConfirmNodeId.value = targetNodeId;
  deleteConfirmNodeLabel.value = fallbackLabel;
  deleteConfirmHasNestedFlow.value = hasNestedChildren;
  deleteConfirmOpen.value = true;
}

function closeDeleteConfirm() {
  if (deleteConfirmBusy.value) {
    return;
  }

  deleteConfirmOpen.value = false;
  deleteConfirmNodeId.value = '';
  deleteConfirmNodeLabel.value = '';
  deleteConfirmHasNestedFlow.value = false;
}

async function confirmDeleteNode() {
  const nodeId = String(deleteConfirmNodeId.value || '').trim();

  if (!nodeId || deleteConfirmBusy.value) {
    return;
  }

  deleteConfirmBusy.value = true;
  debugLogger.log('node:delete-confirmed', {
    node_id: nodeId,
  });

  try {
    store.removeNode(nodeId);
    deleteConfirmBusy.value = false;
    closeDeleteConfirm();
    pushToast(
      __('Action removed locally. Click Save flow to persist changes.', textDomain),
      'success',
      __('Builder', textDomain)
    );
  } catch (error) {
    pushToast(
      error instanceof Error ? error.message : __('Could not delete the action.', textDomain),
      'error',
      __('Builder', textDomain)
    );
    debugLogger.log('node:delete-failed', {
      node_id: nodeId,
      error: error instanceof Error ? error.message : String(error),
    });
  } finally {
    deleteConfirmBusy.value = false;
  }
}

function exportWorkflow() {
  debugLogger.log('workflow:export-requested', {
    workflow_id: store.postId,
  });
  const json = store.exportWorkflowToJson();
  const blob = new Blob([json], { type: 'application/json;charset=utf-8' });
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `${(store.file.post.title || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.json`;
  anchor.click();
  window.URL.revokeObjectURL(url);
}

async function runTest() {
  if (isRunningTest.value) {
    return;
  }

  if (!savedTestPhoneNumber.value) {
    openRunTestModal();
    return;
  }

  debugLogger.log('workflow:test-requested', {
    workflow_id: store.postId,
  });

  try {
    const response = await store.runWorkflowTest();
    pushToast(
      response?.toast_body_title || response?.message || __('Workflow test queued.', textDomain),
      'success',
      __('Builder', textDomain)
    );
  } catch (error) {
    pushToast(
      error instanceof Error ? error.message : __('Could not run the workflow test.', textDomain),
      'error',
      __('Builder', textDomain)
    );
    debugLogger.log('workflow:test-failed', {
      error: error instanceof Error ? error.message : String(error),
    });
  }
}

async function saveTestPhoneAndRun() {
  const nextPhone = String(testPhoneDraft.value || '').trim();

  if (!nextPhone || testPhoneSaving.value) {
    return;
  }

  testPhoneSaving.value = true;
  debugLogger.log('workflow:test-phone-save-start', {
    test_number_phone: nextPhone,
  });

  try {
    const response = await store.saveSettings({ test_number_phone: nextPhone });
    bootstrap.value = {
      ...bootstrap.value,
      settings: cloneValue(response?.settings || {
        ...(bootstrap.value.settings || {}),
        test_number_phone: nextPhone,
      }),
    };
    testPhoneModalOpen.value = false;

    const testResponse = await store.runWorkflowTest();
    pushToast(
      testResponse?.toast_body_title || testResponse?.message || __('Workflow test queued.', textDomain),
      'success',
      __('Builder', textDomain)
    );
  } catch (error) {
    pushToast(
      error instanceof Error ? error.message : __('Could not save the test phone number.', textDomain),
      'error',
      __('Builder', textDomain)
    );
    debugLogger.log('workflow:test-phone-save-failed', {
      error: error instanceof Error ? error.message : String(error),
    });
  } finally {
    testPhoneSaving.value = false;
  }
}

function syncBuilderUrl(postId) {
  if (!postId) {
    return;
  }

  const url = new URL(window.location.href);
  url.searchParams.set('id', String(postId));
  window.history.replaceState({}, '', url.toString());
}

function clearBuilderUrl() {
  const url = new URL(window.location.href);
  url.searchParams.delete('id');
  window.history.replaceState({}, '', url.toString());
}
</script>

<template>
  <Transition name="builder-step" mode="out-in">
    <div v-if="store.step === 'start'" key="start" class="fixed inset-0 z-[999] bg-white text-slate-900" :style="startShellStyle">
      <div class="flex h-full w-full items-center justify-center overflow-hidden">
        <BuilderStartView
          :creating="store.loading.create"
          @scratch="startScratch"
          @template="openTemplateLibrary"
          @import="openImportModal"
          @back="goBack"
        />
      </div>
    </div>

    <div v-else-if="store.step === 'library'" key="library" class="fixed inset-0 z-[999] overflow-y-auto bg-white text-slate-900" :style="startShellStyle">
      <BuilderTemplateLibraryView
        v-model:search="templateSearch"
        v-model:category="templateCategory"
        :category-options="categoryOptions"
        :templates="filteredTemplates"
        :loading="store.loading.templates"
        @select-template="openTemplate"
        @back="goStart"
      />
    </div>

    <div v-else-if="store.step === 'trigger'" key="trigger" class="fixed inset-0 z-[999] overflow-hidden bg-white text-slate-900" :style="startShellStyle">
      <BuilderTriggerSetupView
        :title="store.file.post.title"
        :context="store.activeContext"
        :trigger="store.selectedTrigger"
        :contexts="store.triggerContexts"
        :triggers="store.triggerOptions"
        :loading="store.loading.bootstrap"
        :ready="store.canContinue"
        :continuing="triggerContinuing"
        @update:title="store.setWorkflowTitle"
        @update:context="store.selectTriggerContext"
        @select-trigger="store.selectTrigger"
        @continue="continueFromTriggerSetup"
        @back="goStart"
      />
    </div>

    <BuilderShell v-else key="canvas" :debug-mode="debugMode">
    <template #navbar>
      <BuilderNavbar
        :title="store.file.post.title"
        :status="store.file.post.status"
        :docs-url="docsUrl"
        :loading="isRunningTest"
        :saving="store.loading.save || titleSaving"
        :status-loading="isUpdatingStatus"
        @update:status="handleWorkflowStatusChange"
        @save="saveWorkflow"
        @test="runTest"
        @new="createNewWorkflow"
        @back="goBack"
        @export="exportWorkflow"
        @edit-title="openTitleModal"
      />
    </template>

    <template #main>
      <BuilderCanvasView
        :trigger-node="store.triggerNode"
        :nodes="store.workflowContent"
        :selected-node-id="store.selectedNodeId"
        :selected-node="store.selectedNode"
        :contexts="store.triggerContexts"
        :drawer-open="store.drawerOpen"
        :loading="store.loading.workflow"
        :actions="availableActions"
        :actions-loading="store.loading.actions"
        :actions-open="actionSidebarOpen"
        :flow-ready="canvasFlowReady"
        :ready-trigger="canvasHasTrigger"
        :ready-actions="canvasHasActions"
        :ready-senders="canvasHasSenders"
        @select-node="store.openNodeSettings"
        @add-node="handleActionOpen"
        @duplicate-node="store.duplicateNode"
        @remove-node="openDeleteConfirm"
        @move-node="({ nodeId, direction }) => store.moveNode(nodeId, direction)"
        @update-node="handleNodeUpdate"
        @close-drawer="store.closeNodeSettings"
        @test="runTest"
        @export="exportWorkflow"
        @open-actions="handleActionOpen"
        @select-action="handleActionSelect"
        @close-actions="closeActionSidebar"
      />
    </template>
    </BuilderShell>
  </Transition>

  <BuilderImportModal
    :open="importModalOpen"
    :importing="store.loading.import"
    :file-name="importFileName"
    :error="importError"
    @close="closeImportModal"
    @file="handleImportFile"
    @import="confirmImport"
  />

  <ModalDialog :open="titleModalOpen" :title="__('Edit workflow title', textDomain)" sizeClass="max-w-lg" @close="closeTitleModal">
    <div class="space-y-5">
      <BaseInput v-model="titleDraft" :label="__('Workflow name', textDomain)" />
      <div class="flex items-center justify-end gap-3">
        <BaseButton :title="__('Cancel', textDomain)" variant="ghost" :disabled="isSavingTitle" @click="closeTitleModal" />
        <BaseButton :title="__('Save', textDomain)" :loading="isSavingTitle" @click="saveTitleModal" />
      </div>
    </div>
  </ModalDialog>

  <ModalDialog
    :open="testPhoneModalOpen"
    :title="__('Add test phone number', textDomain)"
    @close="closeTestPhoneModal"
  >
    <div class="space-y-6">
      <div class="grid items-center gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)]">
        <div>
          <h3 class="text-[15px] font-semibold text-slate-800">
            {{ __('Test phone number', textDomain) }}
          </h3>
          <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
            {{ __('Enter a phone number to receive test messages from the builder. Use international format and numbers only.', textDomain) }}
          </p>
        </div>

        <div class="lg:justify-self-start">
          <PhoneField
            v-model="testPhoneDraft"
            :field="{
              placeholder: __('5541987111527', textDomain),
            }"
            :default-country="String(bootstrap?.phones?.default_country_iso2 || '').trim() || 'us'"
            :locale="String(bootstrap?.phones?.locale || 'en_US')"
            :show-header="false"
            name="builder-test-phone"
          />
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
        <BaseButton
          :title="__('Cancel', textDomain)"
          variant="ghost"
          :disabled="testPhoneSaving"
          @click="closeTestPhoneModal"
        />
        <BaseButton
          :title="__('Save and run test', textDomain)"
          :loading="testPhoneSaving"
          :disabled="!String(testPhoneDraft || '').trim()"
          @click="saveTestPhoneAndRun"
        />
      </div>
    </div>
  </ModalDialog>

  <ModalDialog
    :open="deleteConfirmOpen"
    :title="__('Delete action', textDomain)"
    :description="__('This action will be removed from the workflow.', textDomain)"
    sizeClass="max-w-xl"
    @close="closeDeleteConfirm"
  >
    <div class="space-y-6">
      <div class="rounded-[18px] border border-rose-100 bg-rose-50 px-4 py-4">
        <h3 class="text-[15px] font-semibold text-rose-900">
          {{ __('Confirm deletion', textDomain) }}
        </h3>
        <p class="mt-2 text-[13px] leading-6 text-rose-800">
          {{ __('You are about to delete the action', textDomain) }}
          <span class="font-semibold">"{{ deleteConfirmNodeLabel }}"</span>.
          {{ __('This step will be removed from the canvas. Click Save flow to persist this change.', textDomain) }}
          <span v-if="deleteConfirmHasNestedFlow">
            {{ __('Any nested branch inside this action will also be removed.', textDomain) }}
          </span>
        </p>
      </div>

      <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
        <BaseButton
          :title="__('Cancel', textDomain)"
          variant="ghost"
          :disabled="deleteConfirmBusy"
          @click="closeDeleteConfirm"
        />
        <BaseButton
          :title="__('Delete action', textDomain)"
          variant="danger"
          :loading="deleteConfirmBusy"
          @click="confirmDeleteNode"
        />
      </div>
    </div>
  </ModalDialog>

  <ToastStack :toasts="toasts" @dismiss="dismissToast" />
</template>

<style scoped>
.builder-step-enter-active,
.builder-step-leave-active {
  transition:
    opacity 220ms ease,
    transform 220ms ease;
}

.builder-step-enter-from,
.builder-step-leave-to {
  opacity: 0;
  transform: translateY(12px);
}

.builder-step-enter-to,
.builder-step-leave-from {
  opacity: 1;
  transform: translateY(0);
}
</style>
