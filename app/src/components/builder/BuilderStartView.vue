<script setup>
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
