<script setup lang="ts">
/**
 * SessionWindowNotice.vue
 *
 * Warns, while a workflow is being drawn, that the selected action cannot open a
 * conversation on the WhatsApp Cloud API.
 *
 * Meta only accepts free-form content (text, media, interactive messages,
 * reactions) inside the 24-hour window a contact opens by writing first. A
 * workflow that starts the conversation — abandoned cart, "order shipped",
 * post-sale follow-up — has to lead with an approved template, otherwise the
 * send is refused and the failure only shows up in the log.
 *
 * Renders nothing on the legacy relay, which has no such restriction.
 *
 * @since 2.3.0
 */
import { computed } from 'vue';
import BaseAlert from './BaseAlert.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  action: { type: String, default: '' },
});

const store = useWorkflowBuilderStore();

const transport = computed(() => (store.bootstrap?.transport || {}) as Record<string, unknown>);

const templateAction = computed(() => String(transport.value.template_action || 'send_whatsapp_message_template'));

const freeFormActions = computed(() =>
  Array.isArray(transport.value.free_form_actions) ? (transport.value.free_form_actions as string[]) : []
);

const visible = computed(
  () => Boolean(transport.value.requires_template_to_open_window) && freeFormActions.value.includes(props.action)
);

/**
 * Whether the workflow already leads with a template action.
 *
 * When it does the window is opened by the workflow itself, so the notice is
 * informational rather than a problem to fix.
 *
 * @since 2.3.0
 * @returns {boolean} True when a template action exists anywhere in the workflow.
 */
const hasTemplateAction = computed(() => {
  const seen = new Set<string>();
  const stack = [...(store.workflowContent || [])];

  while (stack.length) {
    const node = stack.pop();

    if (!node || seen.has(node.id)) {
      continue;
    }

    seen.add(node.id);

    if (String(node.data?.action || '') === templateAction.value) {
      return true;
    }

    stack.push(...(node.children || []));

    Object.values(node.branches || {}).forEach((branch) => stack.push(...(branch || [])));
  }

  return false;
});
</script>

<template>
  <BaseAlert
    v-if="visible"
    :tone="hasTemplateAction ? 'info' : 'warning'"
    :title="__('This step needs an open conversation', textDomain)"
  >
    <p class="mt-1">
      {{ __('On the official WhatsApp Cloud API, this kind of message only reaches a contact within 24 hours of their last reply. Outside that window Meta refuses it.', textDomain) }}
    </p>

    <p v-if="hasTemplateAction" class="mt-2">
      {{ __('This workflow already has a template step, which opens the window. Keep it before this one.', textDomain) }}
    </p>

    <p v-else class="mt-2">
      {{ __('If this workflow starts the conversation, put a “WhatsApp: Template message” step before this one to open the window.', textDomain) }}
    </p>
  </BaseAlert>
</template>
