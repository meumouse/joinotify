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
    action="dynamic_placeholder"
    badge="Placeholder"
    accent="emerald"
    icon="variable"
    :active="active"
    @click="$emit('click')"
  />
</template>
