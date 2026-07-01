<script setup>
/**
 * TableToolbar.vue
 *
 * Composes the workflows table toolbar by combining the BulkActions selector
 * and PaginationControls into one row, forwarding their props and re-emitting
 * their events up to the parent.
 *
 * @since 2.0.0
 */
import BulkActions from './BulkActions.vue';
import PaginationControls from './PaginationControls.vue';

defineProps({
  bulkAction: { type: String, default: '' },
  bulkOptions: { type: Array, default: () => [] },
  selectedCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  bulkDisabled: { type: Boolean, default: false },
  paginationDisabled: { type: Boolean, default: false },
  pagination: { type: Object, default: () => ({}) },
  summary: { type: String, default: '' },
});

defineEmits(['update:bulkAction', 'applyBulkAction', 'first', 'previous', 'next', 'last']);
</script>

<template>
  <div class="flex flex-col gap-4 my-4 lg:flex-row lg:items-center lg:justify-between">
    <BulkActions
      :disabled="bulkDisabled"
      :loading="loading"
      :model-value="bulkAction"
      :options="bulkOptions"
      :selected-count="selectedCount"
      @apply="$emit('applyBulkAction')"
      @update:modelValue="$emit('update:bulkAction', $event)"
    />

    <PaginationControls
      :current-page="pagination.current_page || 1"
      :disabled="paginationDisabled"
      :summary="summary"
      :total-items="pagination.total_items || 0"
      :total-pages="pagination.total_pages || 1"
      @first="$emit('first')"
      @last="$emit('last')"
      @next="$emit('next')"
      @previous="$emit('previous')"
    />
  </div>
</template>
