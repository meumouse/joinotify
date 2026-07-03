<script setup>
/**
 * WorkflowRowActions.vue
 *
 * Renders the per-row action links for a single workflow, showing edit and
 * move-to-trash for active rows and restore/delete-permanently for trashed
 * rows. Emits the matching action event with the workflow payload.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';

defineProps({
  workflow: { type: Object, required: true },
});

defineEmits(['edit', 'trash', 'restore', 'deletePermanent']);
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 text-sm">
    <button
      v-if="workflow.status !== 'trash'"
      type="button"
      class="font-medium text-ink transition hover:text-primary-800"
      @click="$emit('edit', workflow)"
    >
      {{ __('Edit', textDomain) }}
    </button>

    <button
      v-if="workflow.status !== 'trash'"
      type="button"
      class="font-medium text-danger transition hover:text-danger/80"
      @click="$emit('trash', workflow)"
    >
      {{ __('Move to trash', textDomain) }}
    </button>

    <button
      v-if="workflow.status === 'trash'"
      type="button"
      class="font-medium text-primary-700 transition hover:text-primary-800"
      @click="$emit('restore', workflow)"
    >
      {{ __('Restore', textDomain) }}
    </button>

    <button
      v-if="workflow.status === 'trash'"
      type="button"
      class="font-medium text-danger transition hover:text-danger/80"
      @click="$emit('deletePermanent', workflow)"
    >
      {{ __('Delete permanently', textDomain) }}
    </button>
  </div>
</template>
