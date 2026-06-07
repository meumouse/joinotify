<script setup>
import BaseDrawer from '../base/BaseDrawer.vue';
import NodeSettingsRenderer from './NodeSettingsRenderer.vue';
import { __, textDomain } from '../../utils/i18n';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: () => __('Node settings', textDomain) },
  node: { type: Object, default: null },
  contexts: { type: Array, default: () => [] },
});

defineEmits(['close', 'update']);
</script>

<template>
  <BaseDrawer :open="open" :title="title" @close="$emit('close')">
    <NodeSettingsRenderer :node="node" :contexts="contexts" @update="$emit('update', $event)" />
  </BaseDrawer>
</template>
