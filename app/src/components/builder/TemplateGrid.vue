<script setup>
import TemplateCard from './TemplateCard.vue';

defineProps({
  templates: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['select']);
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <TemplateCard
      v-for="template in templates"
      :key="template.file || template.title"
      :title="template.title"
      :description="template.description"
      :category="template.category"
      :integration="template.integration"
      :trigger="template.trigger"
      :available="template.available"
      @click="$emit('select', template)"
    />
  </div>
</template>
