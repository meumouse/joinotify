<script setup>
/**
 * BuilderStartView.vue
 *
 * Thin wrapper around BuilderStartChooser that forwards the creation option
 * events (scratch, template, import, ai, back) up to the parent. It exists to
 * keep the start screen composable within the builder view hierarchy.
 *
 * @since 2.0.0
 */
import BuilderStartChooser from './BuilderStartChooser.vue';

defineProps({
  creating: { type: Boolean, default: false },
});

defineEmits(['scratch', 'template', 'import', 'ai', 'back']);
</script>

<template>
  <BuilderStartChooser
    :creating="creating"
    @scratch="$emit('scratch')"
    @template="$emit('template')"
    @import="$emit('import')"
    @ai="$emit('ai')"
    @back="$emit('back')"
  />
</template>
