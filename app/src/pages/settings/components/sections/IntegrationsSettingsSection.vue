<script setup>

/**
 * IntegrationsSettingsSection.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import IntegrationCard from '../cards/IntegrationCard.vue';

const props = defineProps({
  integrations: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['toggle', 'configure']);

function isEnabled(key) {
  return (props.settings[key] || 'no') === 'yes';
}
</script>

<template>
  <div class="grid gap-6 max-[1368px]:grid-cols-3 min-[1400px]:grid-cols-4">
    <IntegrationCard
      v-for="card in integrations"
      :key="card.slug"
      :card="card"
      :enabled="isEnabled(card.setting_key)"
      @toggle="$emit('toggle', card.setting_key)"
      @configure="$emit('configure', $event)"
    />
  </div>
</template>
