<script setup lang="ts">
import WorkflowConnector from '../../builder/actions/components/WorkflowConnector.vue';

defineProps({
  branchLabel: { type: String, default: '' },
  compact: { type: Boolean, default: false },
});
</script>

<template>
  <WorkflowConnector :label="branchLabel" :compact="compact" />
</template>
