<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import BaseButton from '../../components/base/BaseButton.vue';
import BaseCard from '../../components/base/BaseCard.vue';
import BaseDialog from '../../components/base/BaseDialog.vue';
import BaseInput from '../../components/base/BaseInput.vue';
import BuilderActionPickerModal from '../../components/builder/BuilderActionPickerModal.vue';
import BuilderCanvasView from '../../components/builder/BuilderCanvasView.vue';
import BuilderLoader from '../../components/builder/BuilderLoader.vue';
import BuilderImportModal from '../../components/builder/BuilderImportModal.vue';
import BuilderNavbar from '../../components/builder/BuilderNavbar.vue';
import BuilderPanel from '../../components/builder/BuilderPanel.vue';
import BuilderShell from '../../components/builder/BuilderShell.vue';
import BuilderStartView from '../../components/builder/BuilderStartView.vue';
import BuilderTemplateLibraryView from '../../components/builder/BuilderTemplateLibraryView.vue';
import BuilderTriggerSetupView from '../../components/builder/BuilderTriggerSetupView.vue';
import { createWorkflowFileFromParts } from '../../parsers/workflowParser';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';
import { cloneValue } from '../../utils/object';

defineOptions({ name: 'BuilderPage' });

const props = defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});

const store = useWorkflowBuilderStore();
const bootstrap = ref(cloneValue(props.bootstrap || {}));
const uiReady = ref(false);
const templateSearch = ref('');
const templateCategory = ref('all');
const importJson = ref('');
const importFileName = ref('');
const importError = ref('');
const importModalOpen = ref(false);
const actionModalOpen = ref(false);
const actionInsertAfterId = ref('');
const titleModalOpen = ref(false);
const titleDraft = ref('');

const templates = computed(() => bootstrap.value.templates || []);
const backUrl = computed(() => bootstrap.value?.links?.back_url || '#');
const docsUrl = computed(() => bootstrap.value?.links?.docs_url || '#');
const debugMode = computed(() => Boolean(bootstrap.value?.debug_mode));
const startShellStyle = computed(() => ({
  top: debugMode.value ? '32px' : '0',
  height: debugMode.value ? 'calc(100vh - 32px)' : '100vh',
}));
const availableActions = computed(() => Object.values(store.actionsCatalog || {}));
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
    store.hydrateFromBootstrap(bootstrap.value);
  },
  { deep: true, immediate: true }
);

onMounted(async () => {
  await nextTick();
  requestAnimationFrame(() => {
    uiReady.value = true;
  });
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

function saveTitleModal() {
  store.setWorkflowTitle(titleDraft.value.trim() || 'New workflow');
  closeTitleModal();
}

async function startScratch() {
  await store.createWorkflowFromScratch(store.file.post.title || 'New workflow');
  syncBuilderUrl(store.postId);
  goTrigger();
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
  actionInsertAfterId.value = afterNodeId || store.selectedNodeId || store.triggerNode?.id || '';
  actionModalOpen.value = true;
}

function handleActionSelect(actionId) {
  store.addActionNode(actionId, actionInsertAfterId.value);
  actionModalOpen.value = false;
  goCanvas();
}

async function createNewWorkflow() {
  await store.createWorkflowFromScratch();
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
  <div v-if="!uiReady" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-50">
    <BuilderLoader />
  </div>

  <template v-if="store.step === 'start'">
    <div class="fixed inset-0 z-[999] bg-white text-slate-900" :style="startShellStyle">
      <div class="flex h-full w-full items-center justify-center overflow-hidden">
        <BuilderStartView
          :creating="store.loading.create"
          @scratch="startScratch"
          @template="goLibrary"
          @import="openImportModal"
          @back="goBack"
        />
      </div>
    </div>
  </template>

  <BuilderShell v-else :debug-mode="debugMode">
    <template #navbar>
      <BuilderNavbar
        :title="store.file.post.title"
        :status="store.file.post.status"
        :docs-url="docsUrl"
        :loading="store.loading.test"
        @update:status="store.setWorkflowStatus"
        @test="runTest"
        @new="createNewWorkflow"
        @back="goBack"
        @export="exportWorkflow"
        @edit-title="openTitleModal"
      />
    </template>

    <template #sidebar>
      <BuilderPanel>
        <div class="space-y-4">
          <BaseCard class="p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Status', textDomain) }}</p>
            <p class="mt-2 text-sm text-slate-700">{{ store.dirty ? __('Unsaved changes', textDomain) : __('Synced', textDomain) }}</p>
            <p v-if="store.errors.length" class="mt-2 text-sm text-rose-600">{{ store.errors[0] }}</p>
            <p v-else-if="store.warnings.length" class="mt-2 text-sm text-amber-600">{{ store.warnings[0] }}</p>
          </BaseCard>

          <BaseCard class="p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Flow', textDomain) }}</p>
            <p class="mt-2 text-sm text-slate-700">{{ store.workflowContent.length }} {{ __('nodes', textDomain) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Category:', textDomain) }} {{ store.file.post.category || __('none', textDomain) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Trigger:', textDomain) }} {{ store.selectedTrigger || __('none', textDomain) }}</p>
          </BaseCard>

          <BaseCard class="p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Quick actions', textDomain) }}</p>
            <div class="mt-3 flex flex-col gap-2">
              <BaseButton :title="__('Save workflow', textDomain)" variant="secondary" :loading="store.loading.save" @click="saveWorkflow" />
              <BaseButton :title="__('Run test', textDomain)" variant="secondary" :loading="store.loading.test" @click="runTest" />
              <BaseButton :title="__('Export workflow', textDomain)" variant="secondary" @click="exportWorkflow" />
            </div>
          </BaseCard>
        </div>
      </BuilderPanel>
    </template>

    <template #main>
      <div class="space-y-6">
        <BuilderTemplateLibraryView
          v-if="store.step === 'library'"
          v-model:search="templateSearch"
          v-model:category="templateCategory"
          :category-options="categoryOptions"
          :templates="filteredTemplates"
          @select-template="openTemplate"
          @back="goStart"
        />

        <BuilderTriggerSetupView
          v-else-if="store.step === 'trigger'"
          :title="store.file.post.title"
          :context="store.activeContext"
          :trigger="store.selectedTrigger"
          :contexts="store.triggerContexts"
          :triggers="store.triggerOptions"
          :ready="store.canContinue"
          @update:title="store.setWorkflowTitle"
          @update:context="store.selectTriggerContext"
          @select-trigger="store.selectTrigger"
          @continue="goCanvas"
          @back="goStart"
        />

        <BuilderCanvasView
          v-else
          :nodes="store.workflowContent"
          :selected-node-id="store.selectedNodeId"
          :selected-node="store.selectedNode"
          :contexts="store.triggerContexts"
          :drawer-open="store.drawerOpen"
          :loading="store.loading.test"
          @select-node="store.openNodeSettings"
          @add-node="handleActionOpen"
          @duplicate-node="store.duplicateNode"
          @remove-node="store.removeNode"
          @update-node="store.updateNodeData(store.editingNodeId || store.selectedNodeId, $event)"
          @close-drawer="store.closeNodeSettings"
          @test="runTest"
          @export="exportWorkflow"
          @open-actions="handleActionOpen"
        />
      </div>
    </template>
  </BuilderShell>

  <BuilderImportModal
    :open="importModalOpen"
    :importing="store.loading.import"
    :file-name="importFileName"
    :error="importError"
    @close="closeImportModal"
    @file="handleImportFile"
    @import="confirmImport"
  />

  <BuilderActionPickerModal
    :open="actionModalOpen"
    :actions="availableActions"
    @close="actionModalOpen = false"
    @select="handleActionSelect"
  />

  <BaseDialog :open="titleModalOpen" :title="__('Edit workflow title', textDomain)" size-class="max-w-lg" @close="closeTitleModal">
    <div class="space-y-5">
      <BaseInput v-model="titleDraft" :label="__('Workflow name', textDomain)" />
      <div class="flex items-center justify-end gap-3">
        <BaseButton :title="__('Cancel', textDomain)" variant="ghost" @click="closeTitleModal" />
        <BaseButton :title="__('Save', textDomain)" @click="saveTitleModal" />
      </div>
    </div>
  </BaseDialog>
</template>
