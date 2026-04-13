<script setup>
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
      Edit
    </button>

    <button
      v-if="workflow.status !== 'trash'"
      type="button"
      class="font-medium text-danger transition hover:text-danger/80"
      @click="$emit('trash', workflow)"
    >
      Move to trash
    </button>

    <button
      v-if="workflow.status === 'trash'"
      type="button"
      class="font-medium text-primary-700 transition hover:text-primary-800"
      @click="$emit('restore', workflow)"
    >
      Restore
    </button>

    <button
      v-if="workflow.status === 'trash'"
      type="button"
      class="font-medium text-danger transition hover:text-danger/80"
      @click="$emit('deletePermanent', workflow)"
    >
      Delete permanently
    </button>
  </div>
</template>
