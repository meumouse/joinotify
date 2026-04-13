<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseInput from '../base/BaseInput.vue';
import BaseTextarea from '../base/BaseTextarea.vue';
import { ACTION_REGISTRY, getActionDefinition } from '../../registries/actionRegistry';
import { getTriggersForContext } from '../../registries/triggerRegistry';

const props = defineProps({
  node: { type: Object, default: null },
  contexts: { type: Array, default: () => [] },
});

const emit = defineEmits(['update']);

function updateField(key, value) {
  emit('update', { [key]: value });
}

function actionDefinition() {
  const actionId = props.node?.data?.action || '';
  return actionId ? getActionDefinition(actionId) : ACTION_REGISTRY[0];
}

function triggerOptions() {
  const context = props.node?.data?.context || '';
  return getTriggersForContext(context);
}
</script>

<template>
  <div v-if="node" class="space-y-4">
    <div class="rounded-lg bg-slate-50 px-4 py-3">
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ __('Selected node', textDomain) }}</p>
      <p class="mt-1 text-sm font-semibold text-slate-900">{{ node.type }}</p>
    </div>

    <template v-if="node.type === 'trigger'">
      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Title', textDomain) }}</span>
        <BaseInput :model-value="node.data?.title || ''" @update:model-value="updateField('title', $event)" />
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Description', textDomain) }}</span>
        <BaseTextarea :model-value="node.data?.description || ''" rows="4" @update:model-value="updateField('description', $event)" />
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Context', textDomain) }}</span>
        <select class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none" :value="node.data?.context || ''" @change="updateField('context', $event.target.value)">
          <option v-for="context in contexts" :key="context.id" :value="context.id">{{ context.label }}</option>
        </select>
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Trigger', textDomain) }}</span>
        <select class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none" :value="node.data?.trigger || ''" @change="updateField('trigger', $event.target.value)">
          <option value="">{{ __('Select trigger', textDomain) }}</option>
          <option v-for="trigger in triggerOptions()" :key="trigger.id" :value="trigger.id">{{ trigger.label }}</option>
        </select>
      </label>
    </template>

    <template v-else-if="node.type === 'action'">
      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Title', textDomain) }}</span>
        <BaseInput :model-value="node.data?.title || ''" @update:model-value="updateField('title', $event)" />
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Preview description', textDomain) }}</span>
        <BaseTextarea :model-value="node.data?.description || ''" rows="4" @update:model-value="updateField('description', $event)" />
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Message', textDomain) }}</span>
        <BaseTextarea :model-value="node.data?.message || ''" rows="7" @update:model-value="updateField('message', $event)" />
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Sender', textDomain) }}</span>
        <BaseInput :model-value="node.data?.sender || ''" @update:model-value="updateField('sender', $event)" />
      </label>

      <label class="block space-y-2">
        <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Recipient', textDomain) }}</span>
        <BaseInput :model-value="node.data?.receiver || ''" @update:model-value="updateField('receiver', $event)" />
      </label>
    </template>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Registry', textDomain) }}</p>
      <p class="mt-1 text-sm text-slate-700">{{ actionDefinition()?.description || __('No registry entry', textDomain) }}</p>
    </div>
  </div>

  <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
    {{ __('Select a node to edit its settings.', textDomain) }}
  </div>
</template>
