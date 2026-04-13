<script setup>
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
  <div class="flex flex-col gap-4 rounded-[8px] border border-slate-200 bg-white p-4 shadow-[0_1px_0_rgba(0,0,0,0.02)] lg:flex-row lg:items-center lg:justify-between">
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
