<script setup lang="ts">
import ActionNode from './ActionNode.vue';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  preview: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <ActionNode
    :title="title"
    :description="preview || description"
    action="stop_funnel"
    badge="Stop"
    accent="rose"
    icon="ban"
    :active="active"
    @click="$emit('click')"
  />
</template>
