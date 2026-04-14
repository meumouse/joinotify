<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../../components/base/BaseButton.vue';
import BaseDialog from '../../components/base/BaseDialog.vue';
import BaseInput from '../../components/base/BaseInput.vue';
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
const routeWorkflowLoaded = ref(false);
const toasts = ref([]);
const toastTimers = new Map();

const templates = computed(() => store.templateCatalog || []);
const backUrl = computed(() => bootstrap.value?.links?.back_url || '#');
const docsUrl = computed(() => bootstrap.value?.links?.docs_url || '#');
const debugMode = computed(() => Boolean(bootstrap.value?.debug_mode));
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
    bootstrap.value = cloneValue(value || {});

    if (workflowIdFromUrl.value > 0) {
      store.setApiFromBootstrap(bootstrap.value);
      store.step = 'canvas';
      return;
    }

    store.hydrateFromBootstrap(bootstrap.value);
  },
  { deep: true, immediate: true }
);

watch(
  () => store.step,
  async (step) => {
    if (step !== 'canvas') {
      return;
    }

    if (workflowIdFromUrl.value <= 0 || routeWorkflowLoaded.value) {
      return;
    }

    const response = await store.loadWorkflowFromServer(workflowIdFromUrl.value);

    if (response && response.ok === false) {
      return;
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
  store.step = 'start';
}

function goLibrary() {
  store.step = 'library';
}

function goTrigger() {
  store.step = 'trigger';
}

function goCanvas() {
  store.step = 'canvas';
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

  if (!store.actionsLoaded) {
    void store.loadCanvasActionsFromServer();
  }
}

function goBack() {
  window.location.href = backUrl.value;
}

function openImportModal() {
  importJson.value = '';
  importFileName.value = '';
  importError.value = '';
  importModalOpen.value = true;
}

function closeImportModal() {
  importModalOpen.value = false;
}

function openTitleModal() {
  titleDraft.value = store.file.post.title || '';
  titleModalOpen.value = true;
}

function closeTitleModal() {
  titleModalOpen.value = false;
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
  const previousTitle = store.file.post.title || '';

  titleSaving.value = true;

  try {
    store.setWorkflowTitle(nextTitle);
    const response = await store.saveWorkflow();
    syncBuilderUrl(response?.workflow?.post_id || store.postId);
    pushToast(__('Workflow name saved successfully.', textDomain), 'success', __('Builder', textDomain));
    closeTitleModal();
  } catch (error) {
    store.setWorkflowTitle(previousTitle);
    pushToast(
      error instanceof Error ? error.message : __('Could not save the workflow name.', textDomain),
      'error',
      __('Builder', textDomain)
    );
  } finally {
    titleSaving.value = false;
  }
}

function workflowStatusToastMessage(response, fallback) {
  return response?.toast_body_title || response?.toast_header_title || fallback;
}

async function handleWorkflowStatusChange(nextStatus) {
  const previousStatus = store.file.post.status || 'draft';

  if (previousStatus === nextStatus) {
    return;
  }

  try {
    const response = await store.updateWorkflowStatus(nextStatus);

    if (!response || response?.ok === false) {
      throw new Error(__('Could not update the workflow status.', textDomain));
    }

    store.setWorkflowStatus(nextStatus);
    pushToast(
      workflowStatusToastMessage(
        response,
        nextStatus === 'publish'
          ? __('The workflow was activated.', textDomain)
          : __('The workflow was deactivated.', textDomain)
      ),
      'success',
      __('Builder', textDomain)
    );
  } catch (error) {
    store.setWorkflowStatus(previousStatus);
    pushToast(
      error instanceof Error ? error.message : __('Could not update the workflow status.', textDomain),
      'error',
      __('Builder', textDomain)
    );
  }
}

async function startScratch() {
  const title = store.file.post.title || 'New workflow';
  store.createEmptyWorkflowFile(title);
  goTrigger();
  store.loading.bootstrap = true;
  await new Promise((resolve) => requestAnimationFrame(() => resolve()));

  try {
    await store.createWorkflowFromScratch(title);
    await store.loadBootstrapFromServer(store.postId);
    syncBuilderUrl(store.postId);
  } catch (error) {
    store.loading.bootstrap = false;
    throw error;
  }
}

async function continueFromTriggerSetup() {
  try {
    await store.saveWorkflow();
    await store.loadBootstrapFromServer(store.postId);
    syncBuilderUrl(store.postId);
    goCanvas();
  } catch (error) {
    console.error(error);
    window.alert(error instanceof Error ? error.message : __('Could not save the selected trigger.', textDomain));
  }
}

async function openTemplateLibrary() {
  goLibrary();
  await store.loadTemplatesFromServer();
}

async function openTemplate(template) {
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
  goCanvas();
}

function handleImportFile(file) {
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
  const result = store.importWorkflowFromJson(importJson.value);

  if (!result.ok) {
    importError.value = result.errors?.[0] || 'Invalid file.';
    return;
  }

  importModalOpen.value = false;
  goCanvas();
}

function handleActionOpen(afterNodeId) {
  openActionSidebar(afterNodeId);
}

function handleActionSelect(actionId) {
  const targetNodeId = actionInsertTarget.value.afterNodeId || store.triggerNode?.id || store.selectedNodeId || '';
  const branchKey = actionInsertTarget.value.branchKey || undefined;

  store.addActionNode(actionId, targetNodeId, branchKey);
  actionModalOpen.value = false;
  actionInsertTarget.value = {
    afterNodeId: '',
    branchKey: '',
  };
  goCanvas();
}

function closeActionSidebar() {
  actionModalOpen.value = false;
  actionInsertTarget.value = {
    afterNodeId: '',
    branchKey: '',
  };
}

async function createNewWorkflow() {
  await store.createWorkflowFromScratch();
  await store.loadBootstrapFromServer(store.postId);
  syncBuilderUrl(store.postId);
  goTrigger();
}

async function saveWorkflow() {
  const response = await store.saveWorkflow();
  syncBuilderUrl(response?.workflow?.post_id || store.postId);
}

function exportWorkflow() {
  const json = store.exportWorkflowToJson();
  const blob = new Blob([json], { type: 'application/json;charset=utf-8' });
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = `${(store.file.post.title || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.json`;
  anchor.click();
  window.URL.revokeObjectURL(url);
}

function runTest() {
  void store.runWorkflowTest();
}

function syncBuilderUrl(postId) {
  if (!postId) {
    return;
  }

  const url = new URL(window.location.href);
  url.searchParams.set('id', String(postId));
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
        :loading="store.loading.test"
        :status-loading="isUpdatingStatus"
        @update:status="handleWorkflowStatusChange"
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
        @select-node="store.openNodeSettings"
        @add-node="handleActionOpen"
        @duplicate-node="store.duplicateNode"
        @remove-node="store.removeNode"
        @move-node="({ nodeId, direction }) => store.moveNode(nodeId, direction)"
        @update-node="store.updateNodeData(store.editingNodeId || store.selectedNodeId, $event)"
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

  <BaseDialog :open="titleModalOpen" :title="__('Edit workflow title', textDomain)" size-class="max-w-lg" @close="closeTitleModal">
    <div class="space-y-5">
      <BaseInput v-model="titleDraft" :label="__('Workflow name', textDomain)" />
      <div class="flex items-center justify-end gap-3">
        <BaseButton :title="__('Cancel', textDomain)" variant="ghost" :disabled="isSavingTitle" @click="closeTitleModal" />
        <BaseButton :title="__('Save', textDomain)" :loading="isSavingTitle" @click="saveTitleModal" />
      </div>
    </div>
  </BaseDialog>

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
