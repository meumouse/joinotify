<script setup lang="ts">
import BaseAlert from '../../components/base/BaseAlert.vue';

defineProps({
  modelValue: { type: Object, default: () => ({}) },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div class="space-y-4">
    <BaseAlert
      tone="neutral"
      title="No configuration required"
      message="This action stops the workflow immediately. There are no editable settings."
    />
  </div>
</template>
