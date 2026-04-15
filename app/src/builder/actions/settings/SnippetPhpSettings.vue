<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseCodeEditorField from '../../components/base/BaseCodeEditorField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue', 'placeholder-selected']);
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      tone="danger"
      title="Security warning"
      message="PHP snippets execute inside the workflow runtime. Only trusted administrators should edit this code."
    />

    <FieldGroup title="PHP snippet" description="The field is required. Keep the code self-contained and deterministic.">
      <BaseCodeEditorField
        :model-value="String(modelValue.snippet_php || '')"
        label="Snippet PHP"
        placeholder="<?php"
        :rows="14"
        @update:model-value="$emit('update:modelValue', { ...modelValue, snippet_php: $event })"
      />
    </FieldGroup>

    <PlaceholderList
      v-if="Array.isArray(availablePlaceholders) && availablePlaceholders.length"
      :placeholders="availablePlaceholders"
      @select="$emit('placeholder-selected', $event)"
    />
  </div>
</template>
