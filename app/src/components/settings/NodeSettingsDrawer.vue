<script setup>
/**
 * NodeSettingsDrawer.vue
 *
 * Modal dialog that hosts the settings editor for a selected workflow node.
 * Derives the dialog title from the node's trigger or action definition and
 * renders NodeSettingsRenderer, forwarding close and update events.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import ModalDialog from '../modals/ModalDialog.vue';
import NodeSettingsRenderer from './NodeSettingsRenderer.vue';
import { getActionDefinition } from '../../registries/actionRegistry';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  node: { type: Object, default: null },
  contexts: { type: Array, default: () => [] },
});

defineEmits(['close', 'update']);

const nodeTitle = computed(() => {
  const node = props.node;

  if (!node) {
    return '';
  }

  if (node.type === 'trigger') {
    const context = String(node.data?.context || '');
    const trigger = String(node.data?.trigger || '');
    const definition = getTriggerDefinition(context, trigger);
    return String(definition?.title || node.data?.title || __('Trigger', textDomain));
  }

  const definition = getActionDefinition(String(node.data?.action || ''));
  return String(definition?.title || node.data?.title || __('Action', textDomain));
});

const resolvedTitle = computed(() => {
  if (props.title) {
    return props.title;
  }

  if (nodeTitle.value) {
    /* translators: %s is the name of the action or trigger being configured. */
    return __('%s settings', textDomain).replace('%s', nodeTitle.value);
  }

  return __('Node settings', textDomain);
});
</script>

<template>
  <ModalDialog :open="open" :title="resolvedTitle" size-class="max-w-2xl" @close="$emit('close')">
    <NodeSettingsRenderer :node="node" :contexts="contexts" @update="$emit('update', $event)" />
  </ModalDialog>
</template>
