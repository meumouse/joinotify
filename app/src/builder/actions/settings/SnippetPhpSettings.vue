<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';
import BaseCodeEditorField from '../../components/base/BaseCodeEditorField.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import PlaceholderList from '../../components/base/PlaceholderList.vue';
import { __, textDomain } from '../../../utils/i18n';

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
      :title="__('Security warning', textDomain)"
      :message="__('PHP snippets execute inside the workflow runtime. Only trusted administrators should edit this code.', textDomain)"
    />

    <FieldGroup :title="__('PHP snippet', textDomain)" :description="__('The field is required. Keep the code self-contained and deterministic.', textDomain)">
      <BaseCodeEditorField
        :model-value="String(modelValue.snippet_php || '')"
        :label="__('Snippet PHP', textDomain)"
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
