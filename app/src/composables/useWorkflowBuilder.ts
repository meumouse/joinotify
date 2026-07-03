/**
 * useWorkflowBuilder.ts
 *
 * Thin composable that exposes the workflow builder store together with a few
 * derived, UI-friendly values (selected node label and sanitized preview) for
 * builder components.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../utils/i18n';
import { useWorkflowBuilderStore } from '../stores/useWorkflowBuilderStore';
import { sanitizePreviewHtml } from '../utils/html';

/**
 * Provides the builder store plus computed helpers for the selected node.
 *
 * @since 2.0.0
 * @returns {Object} The store and derived selected-node values.
 */
export function useWorkflowBuilder() {
  const store = useWorkflowBuilderStore();

  const selectedNodeLabel = computed(() => store.selectedNode?.data?.title || store.selectedNode?.data?.action || __('None selected', textDomain));
  const selectedNodePreview = computed(() => {
    const node = store.selectedNode;
    if (!node) {
      return '';
    }

    if (typeof node.data?.description === 'string' && node.data.description) {
      return sanitizePreviewHtml(node.data.description);
    }

    if (typeof node.data?.message === 'string') {
      return sanitizePreviewHtml(node.data.message);
    }

    return '';
  });

  return {
    store,
    selectedNodeLabel,
    selectedNodePreview,
  };
}
