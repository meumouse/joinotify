<script setup>
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseCheckbox from '../buttons/checkbox/BaseCheckbox.vue';
import WorkflowRowActions from './WorkflowRowActions.vue';
import WorkflowStatusSwitch from './WorkflowStatusSwitch.vue';

const props = defineProps({
  workflow: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  updating: { type: Boolean, default: false },
  formatDate: { type: Function, default: (value) => value },
});

const emit = defineEmits(['select', 'edit', 'trash', 'restore', 'deletePermanent', 'toggleStatus']);

const statusLabel = computed(() => {
  const labels = {
    publish: __('Active', textDomain),
    draft: __('Inactive', textDomain),
    trash: __('In trash', textDomain),
  };

  return labels[props.workflow.status] || props.workflow.status;
});
</script>

<template>
  <tr class="border-t border-slate-200/80 align-top transition hover:bg-slate-50/70">
    <td class="px-4 py-4">
      <BaseCheckbox
        :model-value="selected"
        :aria-label="`${__('Select', textDomain)} ${workflow.name}`"
        :disabled="updating"
        @change="$emit('select', $event)"
      />
    </td>
    <td class="px-4 py-4">
      <div class="space-y-2">
        <a
          class="inline-flex max-w-full items-center gap-2 text-sm font-semibold text-ink hover:text-primary-800"
          :href="workflow.edit_url"
        >
          <span class="truncate">{{ workflow.name }}</span>
        </a>
        <WorkflowRowActions
          class="hidden md:flex"
          :workflow="workflow"
          @deletePermanent="$emit('deletePermanent', $event)"
          @edit="$emit('edit', $event)"
          @restore="$emit('restore', $event)"
          @trash="$emit('trash', $event)"
        />
      </div>
    </td>
    <td class="px-4 py-4 text-sm text-shell-500">
      {{ formatDate(workflow.created_at) }}
    </td>
    <td class="px-4 py-4">
      <WorkflowStatusSwitch
        v-if="workflow.status !== 'trash'"
        :aria-label="`${__('Toggle status for', textDomain)} ${workflow.name}`"
        :disabled="updating"
        :loading="updating"
        :model-value="workflow.status"
        @change="$emit('toggleStatus', $event)"
      />
      <span
        v-else
        class="inline-flex rounded-full bg-shell-50 px-3 py-1 text-xs font-semibold text-shell-500"
      >
        {{ statusLabel }}
      </span>
    </td>
  </tr>
</template>
