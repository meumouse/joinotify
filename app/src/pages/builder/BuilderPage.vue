<script setup>
/**
 * BuilderPage.vue
 *
 * Top-level page component for the workflow builder. Orchestrates the multi-step
 * flow (start, template library, trigger setup, canvas), wires the builder store
 * to child views, and owns page-level concerns: URL sync, unsaved-changes guards,
 * undo/redo shortcuts, toasts, import/AI/title/test modals, and status changes.
 *
 * @since 2.0.0
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { __, sprintf, textDomain } from '../../utils/i18n';
import BaseButton from '../../components/base/BaseButton.vue';
import BaseAlert from '../../builder/components/base/BaseAlert.vue';
import ModalDialog from '../../components/modals/ModalDialog.vue';
import BaseInput from '../../components/base/BaseInput.vue';
import PhoneField from '../../components/fields/PhoneField.vue';
import BuilderCanvasView from '../../components/builder/BuilderCanvasView.vue';
import BuilderImportModal from '../../components/builder/BuilderImportModal.vue';
import BuilderAiGenerateModal from '../../components/builder/BuilderAiGenerateModal.vue';
import BuilderNavbar from '../../components/builder/BuilderNavbar.vue';
import BuilderShell from '../../components/builder/BuilderShell.vue';
import BuilderStartView from '../../components/builder/BuilderStartView.vue';
import BuilderTemplateLibraryView from '../../components/builder/BuilderTemplateLibraryView.vue';
import BuilderTriggerSetupView from '../../components/builder/BuilderTriggerSetupView.vue';
import ToastStack from '../../components/toasts/ToastStack.vue';
import { createWorkflowFileFromParts } from '../../parsers/workflowParser';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';
import { triggerNeedsSetup } from '../../utils/triggerSettings';
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
const importingTemplate = ref('');
const importJson = ref('');
const importFileName = ref('');
const importError = ref('');
const importModalOpen = ref(false);
const aiModalOpen = ref(false);
const aiError = ref('');
const actionModalOpen = ref(false);
const actionInsertTarget = ref({
  afterNodeId: '',
  branchKey: '',
});
const titleModalOpen = ref(false);
const titleDraft = ref('');
const titleSaving = ref(false);
const triggerContinuing = ref(false);
// True while the trigger-setup view was opened to change an existing flow's
// trigger (via the node menu), so we update it in place instead of recreating
// the workflow and losing the existing actions. Mirrored to the `change_trigger`
// URL param so the trigger screen can show a close button that returns to the
// canvas (and the mode survives an accidental reload of the trigger screen).
const changingTrigger = ref(readChangeTriggerFlag());
const testPhoneModalOpen = ref(false);
const testPhoneDraft = ref('');
const testPhoneSaving = ref(false);
const routeWorkflowLoaded = ref(false);
const autoOpenedTriggerSetupId = ref('');
// Warns the user when the loaded flow's trigger/integration is unavailable. The
// `handledKey` makes the modal fire once per distinct trigger+reason so dismissing
// it does not re-open on unrelated canvas re-renders.
const triggerWarningModalOpen = ref(false);
const triggerWarningHandledKey = ref('');
// Confirmation shown when the user tries to leave the builder while the flow has
// unsaved changes (store.dirty). Offers save-and-leave, leave-anyway, or cancel.
const leaveConfirmOpen = ref(false);
const leaving = ref(false);
// Set right before we intentionally navigate away from a dirty flow so the
// beforeunload guard does not additionally raise the native browser prompt.
const skipUnloadGuard = ref(false);
const toasts = ref([]);
const toastTimers = new Map();

const templates = computed(() => store.templateCatalog || []);
const backUrl = computed(() => bootstrap.value?.links?.back_url || '#');
const dashboardUrl = computed(() => bootstrap.value?.links?.dashboard_url || bootstrap.value?.links?.back_url || '#');
const docsUrl = computed(() => bootstrap.value?.links?.docs_url || '#');
const settingsUrl = computed(() => store.bootstrap?.links?.settings_url || bootstrap.value?.links?.settings_url || '#');
// URL for starting a fresh workflow: the current builder page without the
// workflow-specific query params. Mirrors what createNewWorkflow() does in the
// SPA, but as a real href so the item can be opened in a new tab.
const newWorkflowUrl = computed(() => {
  if (typeof window === 'undefined') {
    return '#';
  }

  const url = new URL(window.location.href);
  url.searchParams.delete('id');
  url.searchParams.delete('change_trigger');
  return url.toString();
});
// Backend-authoritative assessment of the loaded flow's trigger: the frontend
// catalog re-injects core contexts as enabled, so only the server can reliably
// tell whether the trigger's integration is actually active and registered.
const triggerAvailability = computed(() => {
  const data = store.bootstrap?.trigger_availability;
  return data && typeof data === 'object' ? data : null;
});
const triggerWarningTitle = computed(() => {
  const reason = triggerAvailability.value?.reason || '';

  if (reason === 'trigger_not_found') {
    return __('Trigger no longer available', textDomain);
  }

  return __('Integration not active', textDomain);
});
const triggerWarningMessage = computed(() => {
  const availability = triggerAvailability.value;

  if (!availability) {
    return '';
  }

  const label = String(availability.integration_label || '').trim();

  switch (availability.reason) {
    case 'plugin_inactive':
      return sprintf(
        // translators: %s is the integration name (e.g. WooCommerce).
        __('The %s plugin this trigger depends on is not installed or active. This flow will not be processed until the plugin is active again.', textDomain),
        label
      );
    case 'integration_disabled':
      return sprintf(
        // translators: %s is the integration name (e.g. WooCommerce).
        __('The %s integration that this flow\'s trigger depends on is currently disabled. This flow will not be processed until the integration is enabled again.', textDomain),
        label
      );
    case 'trigger_not_found':
      return __('The trigger used by this flow is no longer registered. This flow will not be processed until you select an available trigger.', textDomain);
    case 'integration_unavailable':
    default:
      return label
        ? sprintf(
            // translators: %s is the integration name (e.g. WooCommerce).
            __('The %s integration that this flow\'s trigger depends on is not available. This flow will not be processed until the integration is available again.', textDomain),
            label
          )
        : __('The integration that this flow\'s trigger depends on is not available. This flow will not be processed until the integration is available again.', textDomain);
  }
});
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
// The navbar must not share the loader's full readiness gate: `canvasFlowReady`
// stays false whenever no sender is connected, which would hide the entire
// header (back, save, test, Add action) forever. The full-screen canvas loader
// is an opaque overlay that already covers the navbar during a genuine load, so
// gating only on the real workflow-loading flag is enough — once loading ends
// the navbar is revealed regardless of whether the flow is fully configured.
const hideCanvasNavbar = computed(() => store.loading.workflow);
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
      // The bootstrap prop is already a fresh GET of /admin/builder?id=N, so hydrate
      // directly from it and mark the route loaded to avoid a duplicate server fetch.
      // Reloading straight to the canvas means any leftover change-trigger intent is
      // stale, so drop it (and its URL param) before showing the flow.
      changingTrigger.value = false;
      setChangeTriggerUrl(false);
      store.step = 'canvas';
      store.hydrateFromBootstrap(bootstrap.value);
      routeWorkflowLoaded.value = true;
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

// Triggers with required settings (e.g. "Order status changed") must be configured
// before the workflow runs correctly. Open their settings drawer automatically the
// first time the canvas is ready and the required configuration is still missing.
watch(
  () => [store.step, canvasFlowReady.value, store.triggerNode?.id, store.loading.workflow],
  () => {
    if (store.step !== 'canvas' || store.loading.workflow || !canvasFlowReady.value) {
      return;
    }

    const triggerNode = store.triggerNode;

    if (!triggerNode) {
      return;
    }

    if (autoOpenedTriggerSetupId.value === triggerNode.id) {
      return;
    }

    const definition = store.getTriggerDefinition(
      String(triggerNode.data?.context ?? ''),
      String(triggerNode.data?.trigger ?? ''),
    );

    if (!triggerNeedsSetup(triggerNode, definition)) {
      return;
    }

    autoOpenedTriggerSetupId.value = triggerNode.id;
    debugLogger.log('trigger:auto-open-required-settings', {
      node_id: triggerNode.id,
      trigger: String(triggerNode.data?.trigger ?? ''),
    });
    store.openNodeSettings(triggerNode.id);
  },
  { immediate: true }
);

// Warn once when an existing flow is opened on the canvas with a trigger whose
// integration is disabled/unavailable or whose trigger is no longer registered —
// such a flow runs into nothing, so the user needs to know it may fail.
watch(
  () => [store.step, store.loading.workflow, triggerAvailability.value],
  () => {
    if (store.step !== 'canvas' || store.loading.workflow) {
      return;
    }

    const availability = triggerAvailability.value;

    if (!availability || !availability.has_trigger || availability.available) {
      return;
    }

    const handledKey = `${availability.context}:${availability.trigger}:${availability.reason}`;

    if (triggerWarningHandledKey.value === handledKey) {
      return;
    }

    triggerWarningHandledKey.value = handledKey;
    triggerWarningModalOpen.value = true;
    debugLogger.log('trigger:unavailable-warning', {
      context: availability.context,
      trigger: availability.trigger,
      reason: availability.reason,
    });
  },
  { immediate: true, deep: true }
);

function isEditableTarget(target) {
  const element = target instanceof HTMLElement ? target : null;

  if (!element) {
    return false;
  }

  if (element.isContentEditable) {
    return true;
  }

  return ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName);
}

function handleHistoryShortcut(event) {
  if (!event.ctrlKey && !event.metaKey) {
    return;
  }

  const key = String(event.key || '').toLowerCase();
  const isUndo = key === 'z' && !event.shiftKey;
  const isRedo = (key === 'z' && event.shiftKey) || key === 'y';

  if (!isUndo && !isRedo) {
    return;
  }

  // Let the browser handle native undo/redo while typing in a field.
  if (isEditableTarget(event.target)) {
    return;
  }

  event.preventDefault();

  if (isUndo) {
    store.undo();
  } else {
    store.redo();
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleHistoryShortcut);
  window.addEventListener('beforeunload', handleBeforeUnload);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleHistoryShortcut);
  window.removeEventListener('beforeunload', handleBeforeUnload);

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

function goChangeTrigger() {
  debugLogger.log('trigger:change-requested', {
    node_id: store.triggerNode?.id || '',
  });
  store.closeNodeSettings();
  changingTrigger.value = true;
  setChangeTriggerUrl(true);
  store.step = 'trigger';
}

function closeTriggerWarning() {
  triggerWarningModalOpen.value = false;
}

function changeTriggerFromWarning() {
  triggerWarningModalOpen.value = false;
  goChangeTrigger();
}

function openIntegrationsSettings() {
  // Land directly on the integrations tab: the settings SPA restores its active
  // section from this storage key on load (it has no URL-param routing).
  try {
    window.localStorage.setItem('joinotify-settings-active-section', 'integrations');
  } catch (error) {
    // Ignore storage failures (private mode, disabled storage): the user can
    // still navigate to the integrations tab manually.
  }

  window.location.href = settingsUrl.value;
}

function handleTriggerBack() {
  if (changingTrigger.value) {
    changingTrigger.value = false;
    setChangeTriggerUrl(false);
    goCanvas();
    return;
  }

  goStart();
}

// Close (X) on the trigger screen. When changing an existing flow's trigger it
// returns to the canvas; for a new, unsaved flow (opened from the builder
// without a persisted id) it dismisses back to the start screen.
function closeTriggerSetup() {
  debugLogger.log('trigger:change-cancelled', {
    node_id: store.triggerNode?.id || '',
  });

  if (changingTrigger.value) {
    changingTrigger.value = false;
    setChangeTriggerUrl(false);
    goCanvas();
    return;
  }

  goStart();
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

// Warns the browser (tab close / reload / native back) about unsaved changes.
// Skipped once we deliberately leave a dirty flow via the confirmation modal so
// the user is not prompted twice.
function handleBeforeUnload(event) {
  if (!store.dirty || skipUnloadGuard.value) {
    return;
  }

  event.preventDefault();
  // Legacy browsers require returnValue to be set to trigger the prompt.
  event.returnValue = '';
  return '';
}

function performBack() {
  debugLogger.log('navigation:back', {
    url: backUrl.value,
  });
  // Bypass the beforeunload guard: the user already confirmed leaving.
  skipUnloadGuard.value = true;
  window.location.href = backUrl.value;
}

function goBack() {
  if (store.dirty) {
    debugLogger.log('navigation:back-blocked-dirty');
    leaveConfirmOpen.value = true;
    return;
  }

  performBack();
}

function cancelLeave() {
  if (leaving.value) {
    return;
  }

  leaveConfirmOpen.value = false;
}

function confirmLeaveWithoutSaving() {
  debugLogger.log('navigation:leave-without-saving');
  leaveConfirmOpen.value = false;
  performBack();
}

async function saveAndLeave() {
  if (leaving.value) {
    return;
  }

  leaving.value = true;
  debugLogger.log('workflow:save-and-leave-requested');

  try {
    const response = await store.saveWorkflow();
    syncBuilderUrl(response?.workflow?.post_id || store.postId);
    leaveConfirmOpen.value = false;
    performBack();
  } catch (error) {
    leaving.value = false;
    pushToast(
      error instanceof Error ? error.message : __('Could not save the workflow.', textDomain),
      'error',
      __('Builder', textDomain)
    );
    debugLogger.log('workflow:save-and-leave-failed', {
      error: error instanceof Error ? error.message : String(error),
    });
  }
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

function openAiModal() {
  debugLogger.log('modal:open-ai');
  aiError.value = '';
  aiModalOpen.value = true;
}

function closeAiModal() {
  if (store.loading.create) {
    return;
  }

  debugLogger.log('modal:close-ai');
  aiModalOpen.value = false;
}

async function handleAiGenerate(payload) {
  aiError.value = '';
  debugLogger.log('ai:generate-requested');

  try {
    const result = await store.generateWorkflowFromAi(payload || {});

    if (!result || result.ok === false) {
      aiError.value = result?.message || __('The AI could not generate the workflow. Please try again.', textDomain);
      return;
    }

    aiModalOpen.value = false;
    routeWorkflowLoaded.value = false;
    clearBuilderUrl();
    goCanvas();
    pushToast(
      __('Workflow generated. Review the steps and click Save flow to keep it.', textDomain),
      'success',
      __('Builder', textDomain),
    );
  } catch (error) {
    aiError.value = error instanceof Error ? error.message : __('The AI could not generate the workflow.', textDomain);
    debugLogger.log('ai:generate-failed', {
      error: error instanceof Error ? error.message : String(error),
    });
  }
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
  const nextTitle = titleDraft.value.trim() || __('New workflow', textDomain);
  debugLogger.log('workflow:title-save-requested', {
    next_title: nextTitle,
    previous_title: store.file.post.title || '',
  });

  store.setWorkflowTitle(nextTitle);
  pushToast(__('Workflow name updated. Click Save flow to persist changes.', textDomain), 'info', __('Builder', textDomain));
  closeTitleModal();
}

async function handleWorkflowStatusChange(nextStatus) {
  const previousStatus = store.file.post.status || 'draft';

  if (previousStatus === nextStatus) {
    return;
  }

  debugLogger.log('workflow:status-change-requested', {
    from: previousStatus,
    to: nextStatus,
  });

  if (store.postId <= 0) {
    pushToast(
      __('Save the workflow before activating it.', textDomain),
      'warning',
      __('Builder', textDomain)
    );
    return;
  }

  try {
    const response = await store.updateWorkflowStatus(nextStatus);

    if (!response || response?.ok === false) {
      throw new Error(__('Could not update the workflow status.', textDomain));
    }

    pushToast(
      nextStatus === 'publish'
        ? __('Workflow activated.', textDomain)
        : __('Workflow deactivated.', textDomain),
      'success',
      __('Builder', textDomain)
    );
  } catch (error) {
    console.error(error);
    debugLogger.log('workflow:status-change-failed', {
      from: previousStatus,
      to: nextStatus,
      error: error instanceof Error ? error.message : String(error),
    });
    pushToast(
      __('Could not update the workflow status. Please try again.', textDomain),
      'error',
      __('Builder', textDomain)
    );
  }
}

async function startScratch() {
  const title = store.file.post.title || __('New workflow', textDomain);
  debugLogger.log('start:scratch', { title });
  store.createEmptyWorkflowFile(title);
  goTrigger();
}

async function continueFromTriggerSetup() {
  debugLogger.log('trigger:continue');

  // Changing the trigger of an existing flow: the trigger node was already
  // updated in place (selectTrigger / selectTriggerContext), so just return to
  // the canvas without recreating the workflow — keeps the other actions.
  if (changingTrigger.value) {
    changingTrigger.value = false;
    setChangeTriggerUrl(false);
    // The new trigger/context (and its action catalog) were applied in memory by
    // selectTriggerContext/selectTrigger and are not persisted yet. Mark the route
    // as already loaded so the step watcher does NOT reload the bootstrap from the
    // server on the way back to the canvas — that reload re-hydrates the saved
    // (old) context and would revert the trigger change and stale the action catalog.
    routeWorkflowLoaded.value = true;
    debugLogger.log('trigger:changed-in-place', {
      node_id: store.triggerNode?.id || '',
      trigger: store.selectedTrigger,
    });
    goCanvas();
    return;
  }

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
  if (importingTemplate.value) {
    return;
  }

  debugLogger.log('templates:select-template', {
    title: template?.title || '',
    file: template?.file || '',
  });

  importingTemplate.value = template.file || template.title || '';

  try {
    if (template.file) {
      await store.createWorkflowFromTemplate(template.file, template.title || '');
    } else if (Array.isArray(template.workflow_content)) {
      store.loadWorkflowFile(
        createWorkflowFileFromParts({
          plugin_version: bootstrap.value.version || '1.0.0',
          post: {
            type: 'joinotify-workflow',
            title: template.title || __('Template', textDomain),
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
  } finally {
    importingTemplate.value = '';
  }
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
    importError.value = __('Could not read the selected file.', textDomain);
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
    importError.value = result.errors?.[0] || __('Invalid file.', textDomain);
    return;
  }

  importModalOpen.value = false;
  // Clear the selection so reopening the modal starts clean instead of showing the
  // previous file name with a pre-armed Import button pointing at stale JSON.
  importJson.value = '';
  importFileName.value = '';
  importError.value = '';
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

// Deletion is no longer gated behind a confirmation dialog: the builder now
// supports undo (Ctrl+Z), so an accidental removal can be reverted instantly.
function handleRemoveNode(nodeId) {
  const targetNodeId = String(nodeId || '').trim();

  if (!targetNodeId || targetNodeId === store.triggerNode?.id) {
    return;
  }

  debugLogger.log('node:delete', {
    node_id: targetNodeId,
  });

  try {
    store.removeNode(targetNodeId);
  } catch (error) {
    pushToast(
      error instanceof Error ? error.message : __('Could not delete the action.', textDomain),
      'error',
      __('Builder', textDomain)
    );
    debugLogger.log('node:delete-failed', {
      node_id: targetNodeId,
      error: error instanceof Error ? error.message : String(error),
    });
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
  // Anchor must be in the DOM for the click to trigger a download in Firefox, and
  // the object URL must outlive the click — revoking it synchronously can abort
  // the download before the browser has read the blob (Firefox / Chrome under load).
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  setTimeout(() => window.URL.revokeObjectURL(url), 0);
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

function readChangeTriggerFlag() {
  if (typeof window === 'undefined') {
    return false;
  }

  return new URL(window.location.href).searchParams.get('change_trigger') === '1';
}

function setChangeTriggerUrl(active) {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);

  if (active) {
    url.searchParams.set('change_trigger', '1');
  } else {
    url.searchParams.delete('change_trigger');
  }

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
          @ai="openAiModal"
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
        :importing-template="importingTemplate"
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
        :show-close="true"
        @update:title="store.setWorkflowTitle"
        @update:context="store.selectTriggerContext"
        @select-trigger="store.selectTrigger"
        @continue="continueFromTriggerSetup"
        @back="handleTriggerBack"
        @close="closeTriggerSetup"
      />
    </div>

    <BuilderShell v-else key="canvas" :debug-mode="debugMode" :hide-navbar="hideCanvasNavbar">
    <template #navbar>
      <BuilderNavbar
        :title="store.file.post.title"
        :status="store.file.post.status"
        :docs-url="docsUrl"
        :new-url="newWorkflowUrl"
        :back-url="backUrl"
        :dashboard-url="dashboardUrl"
        :settings-url="settingsUrl"
        :loading="isRunningTest"
        :saving="store.loading.save || titleSaving"
        :dirty="store.dirty"
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
        :editor-notes="store.editorNotes"
        :selected-node-id="store.selectedNodeId"
        :selected-node="store.selectedNode"
        :contexts="store.triggerContexts"
        :drawer-open="store.drawerOpen"
        :loading="store.loading.workflow"
        :actions="availableActions"
        :action-categories="store.actionCategories"
        :actions-loading="store.loading.actions"
        :actions-open="actionSidebarOpen"
        :flow-ready="canvasFlowReady"
        :ready-trigger="canvasHasTrigger"
        :ready-actions="canvasHasActions"
        :ready-senders="canvasHasSenders"
        :can-undo="store.canUndo"
        :can-redo="store.canRedo"
        @undo="store.undo"
        @redo="store.redo"
        @select-node="store.openNodeSettings"
        @change-trigger="goChangeTrigger"
        @add-node="handleActionOpen"
        @duplicate-node="store.duplicateNode"
        @remove-node="handleRemoveNode"
        @move-node="({ nodeId, direction }) => store.moveNode(nodeId, direction)"
        @update-node="handleNodeUpdate"
        @add-note="(position) => store.addEditorNote(position)"
        @update-note="({ id, patch }) => store.updateEditorNote(id, patch)"
        @remove-note="(id) => store.removeEditorNote(id)"
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
    @error="importError = $event"
  />

  <BuilderAiGenerateModal
    :open="aiModalOpen"
    :loading="store.loading.create"
    :error="aiError"
    @close="closeAiModal"
    @generate="handleAiGenerate"
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
    :open="triggerWarningModalOpen"
    :title="triggerWarningTitle"
    :eyebrow="__('Trigger warning', textDomain)"
    sizeClass="max-w-xl"
    @close="closeTriggerWarning"
  >
    <div class="space-y-6">
      <BaseAlert tone="warning" :message="triggerWarningMessage" />

      <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
        <BaseButton :title="__('Dismiss', textDomain)" variant="ghost" @click="closeTriggerWarning" />
        <BaseButton :title="__('Open integration settings', textDomain)" variant="secondary" @click="openIntegrationsSettings" />
        <BaseButton :title="__('Change trigger', textDomain)" @click="changeTriggerFromWarning" />
      </div>
    </div>
  </ModalDialog>

  <ModalDialog
    :open="leaveConfirmOpen"
    :title="__('Leave without saving?', textDomain)"
    :eyebrow="__('Unsaved changes', textDomain)"
    sizeClass="max-w-lg"
    @close="cancelLeave"
  >
    <div class="space-y-6">
      <p class="text-[13px] leading-5 text-slate-500">
        {{ __('This workflow has unsaved changes. If you leave now, your changes will be lost.', textDomain) }}
      </p>

      <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
        <BaseButton :title="__('Cancel', textDomain)" variant="ghost" :disabled="leaving" @click="cancelLeave" />
        <BaseButton :title="__('Leave without saving', textDomain)" variant="secondary" :disabled="leaving" @click="confirmLeaveWithoutSaving" />
        <BaseButton :title="__('Save and leave', textDomain)" :loading="leaving" @click="saveAndLeave" />
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
