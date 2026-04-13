<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';
import BaseDialog from '../base/BaseDialog.vue';

defineProps({
  open: { type: Boolean, default: false },
  actions: { type: Array, default: () => [] },
});

defineEmits(['close', 'select']);
</script>

<template>
  <BaseDialog :open="open" :title="__('Available actions', textDomain)" size-class="max-w-3xl" @close="$emit('close')">
    <div class="space-y-5">
      <p class="text-sm leading-6 text-slate-500">{{ __('Choose an action to insert below the selected block.', textDomain) }}</p>

      <div class="grid gap-3 md:grid-cols-2">
        <button
          v-for="action in actions"
          :key="action.id"
          type="button"
          class="rounded-[24px] border border-slate-200 bg-white p-5 text-left transition hover:border-slate-300 hover:shadow-sm"
          @click="$emit('select', action.id)"
        >
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ action.context || action.category || __('Action', textDomain) }}</p>
          <h3 class="mt-2 text-base font-semibold text-slate-900">{{ action.label }}</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500">{{ action.description }}</p>
        </button>
      </div>

      <div class="flex justify-end">
        <BaseButton :title="__('Close', textDomain)" variant="ghost" @click="$emit('close')" />
      </div>
    </div>
  </BaseDialog>
</template>
