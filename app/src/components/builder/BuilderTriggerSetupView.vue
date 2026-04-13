<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseCard from '../base/BaseCard.vue';
import TriggerCard from './TriggerCard.vue';
import TriggerStepFooter from './TriggerStepFooter.vue';
import WorkflowNameField from './WorkflowNameField.vue';

defineProps({
  title: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  contexts: { type: Array, default: () => [] },
  triggers: { type: Array, default: () => [] },
  ready: { type: Boolean, default: false },
});

defineEmits(['update:title', 'update:context', 'select-trigger', 'continue', 'back']);
</script>

<template>
  <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
    <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
      <aside class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Categories', textDomain) }}</p>
        <div class="mt-4 space-y-2">
          <button
            v-for="item in contexts"
            :key="item.id"
            type="button"
            class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm transition"
            :class="context === item.id ? 'border-primary-200 bg-primary-50 text-primary-950 shadow-soft' : 'border-transparent bg-white/70 text-slate-600 hover:border-primary-200 hover:bg-white'"
            @click="$emit('update:context', item.id)"
          >
            <span class="font-medium">{{ item.label }}</span>
            <span class="text-xs uppercase tracking-[0.18em] text-slate-400">{{ item.id }}</span>
          </button>
        </div>
      </aside>

      <div class="space-y-6">
        <div class="max-w-2xl">
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Trigger setup', textDomain) }}</p>
          <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ __('Define a name for this workflow', textDomain) }}</h2>
          <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('The name is used for internal workflow tracking.', textDomain) }}</p>
        </div>

        <BaseCard class="p-5">
          <WorkflowNameField :model-value="title" @update:model-value="$emit('update:title', $event)" />
        </BaseCard>

        <div>
          <p class="text-sm font-semibold text-slate-900">{{ __('Choose the starting trigger', textDomain) }}</p>
          <p class="mt-1 text-sm text-slate-500">{{ __('The cards below belong to the integration selected in the sidebar.', textDomain) }}</p>
        </div>

        <div class="grid gap-3 xl:grid-cols-2">
          <TriggerCard
            v-for="item in triggers"
            :key="item.id"
            :title="item.label"
            :description="item.description"
            :selected="trigger === item.id"
            @click="$emit('select-trigger', item.id)"
          />
        </div>

        <TriggerStepFooter :disabled="!ready" @continue="$emit('continue')" @back="$emit('back')" />
      </div>
    </div>
  </section>
</template>
