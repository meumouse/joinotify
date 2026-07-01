<script setup>
/**
 * TriggerList.vue
 *
 * Renders a vertical list of TriggerCard components for the provided triggers,
 * marking the currently selected one and emitting a `select` event with the
 * chosen trigger id.
 *
 * @since 2.0.0
 */
import TriggerCard from './TriggerCard.vue';

defineProps({
  triggers: { type: Array, default: () => [] },
  selected: { type: String, default: '' },
});

defineEmits(['select']);
</script>

<template>
  <div class="grid gap-3">
    <TriggerCard
      v-for="trigger in triggers"
      :key="trigger.id"
      :title="trigger.label"
      :description="trigger.description"
      :selected="selected === trigger.id"
      @click="$emit('select', trigger.id)"
    />
  </div>
</template>
