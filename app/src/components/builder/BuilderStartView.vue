<script setup>
import BuilderStartChooser from './BuilderStartChooser.vue';

defineProps({
  creating: { type: Boolean, default: false },
});

defineEmits(['scratch', 'template', 'import', 'back']);
</script>

<template>
  <BuilderStartChooser
    :creating="creating"
    @scratch="$emit('scratch')"
    @template="$emit('template')"
    @import="$emit('import')"
    @back="$emit('back')"
  />
</template>
