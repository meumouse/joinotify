<script setup>
import BaseCheckbox from '../buttons/checkbox/BaseCheckbox.vue';
import WorkflowTableHeader from './WorkflowTableHeader.vue';
import WorkflowTableRow from './WorkflowTableRow.vue';

defineProps({
  workflows: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  allSelected: { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
  loadingIds: { type: Array, default: () => [] },
  formatDate: { type: Function, default: (value) => value },
});

defineEmits(['toggleAll', 'select', 'edit', 'trash', 'restore', 'deletePermanent', 'toggleStatus']);
</script>

<template>
  <div class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)]">
    <div class="hidden md:block">
      <table class="min-w-full border-collapse">
        <WorkflowTableHeader
          :all-selected="allSelected"
          :indeterminate="indeterminate"
          @toggleAll="$emit('toggleAll', $event)"
        />
        <tbody class="bg-white">
          <WorkflowTableRow
            v-for="workflow in workflows"
            :key="workflow.id"
            :format-date="formatDate"
            :selected="selectedIds.includes(String(workflow.id))"
            :updating="loadingIds.includes(String(workflow.id))"
            :workflow="workflow"
            @deletePermanent="$emit('deletePermanent', $event)"
            @edit="$emit('edit', $event)"
            @restore="$emit('restore', $event)"
            @select="$emit('select', workflow, $event)"
            @toggleStatus="$emit('toggleStatus', workflow, $event)"
            @trash="$emit('trash', $event)"
          />
        </tbody>
      </table>
    </div>

    <div class="space-y-3 p-3 md:hidden">
      <details
        v-for="workflow in workflows"
        :key="workflow.id"
        class="group rounded-[8px] border border-slate-200 bg-white p-4 shadow-[0_1px_0_rgba(0,0,0,0.02)]"
      >
        <summary class="flex cursor-pointer list-none items-center gap-3 outline-none">
          <BaseCheckbox
            :model-value="selectedIds.includes(String(workflow.id))"
            :aria-label="`Select ${workflow.name}`"
            :disabled="loadingIds.includes(String(workflow.id))"
            @click.stop
            @change="$emit('select', workflow, $event)"
          />

          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-ink">{{ workflow.name }}</p>
            <p class="text-xs text-shell-500">{{ formatDate(workflow.created_at) }}</p>
          </div>

          <span
            v-if="workflow.status === 'trash'"
            class="inline-flex rounded-full bg-shell-50 px-3 py-1 text-xs font-semibold text-shell-500"
          >
            In trash
          </span>
          <WorkflowStatusSwitch
            v-else
            :aria-label="`Toggle status for ${workflow.name}`"
            :disabled="loadingIds.includes(String(workflow.id))"
            :loading="loadingIds.includes(String(workflow.id))"
            :model-value="workflow.status"
            @click.stop
            @change="$emit('toggleStatus', workflow, $event)"
          />
        </summary>

        <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
          <div class="flex items-center justify-between text-sm text-shell-500">
            <span>Created</span>
            <span class="font-medium text-ink">{{ formatDate(workflow.created_at) }}</span>
          </div>
          <WorkflowRowActions
            :workflow="workflow"
            @deletePermanent="$emit('deletePermanent', $event)"
            @edit="$emit('edit', $event)"
            @restore="$emit('restore', $event)"
            @trash="$emit('trash', $event)"
          />
        </div>
      </details>
    </div>
  </div>
</template>
