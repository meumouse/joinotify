<script setup lang="ts">
import { computed } from 'vue';
import { useActionRegistry } from '../../builder/actions/composables/useActionRegistry';
import ActionNode from './ActionNode.vue';
import { resolveSvgMarkup } from '../../utils/icon';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  preview: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click']);

const registry = useActionRegistry();
const resolvedIconSvg = computed(() => {
  registry.revision.value;
  const definition = registry.get('stop_funnel');
  return resolveSvgMarkup(definition?.iconSvg, definition?.icon);
});
</script>

<template>
  <ActionNode
    :title="title"
    :description="preview || description"
    action="stop_funnel"
    badge="Stop"
    accent="rose"
    icon="ban"
    :icon-svg="resolvedIconSvg"
    :active="active"
    @click="$emit('click')"
  />
</template>
