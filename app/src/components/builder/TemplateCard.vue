<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  integration: { type: String, default: '' },
  trigger: { type: String, default: '' },
  available: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <article class="flex h-full flex-col rounded-[10px] border border-slate-200 bg-white p-5 text-left transition duration-150 hover:border-primary-200 hover:shadow-[0_12px_30px_rgba(15,23,42,0.06)]">
    <h3 class="text-[18px] font-semibold leading-6 tracking-tight text-slate-700">
      {{ title }}
    </h3>

    <p v-if="description" class="mt-3 text-sm leading-6 text-slate-500">
      {{ description }}
    </p>

    <div class="mt-4 space-y-1.5 text-sm leading-6 text-slate-500">
      <p>
        <span class="font-semibold text-slate-600">{{ __('Integration:', textDomain) }}</span>
        {{ integration || __('No integration', textDomain) }}
      </p>
      <p>
        <span class="font-semibold text-slate-600">{{ __('Trigger:', textDomain) }}</span>
        {{ trigger || __('No trigger', textDomain) }}
      </p>
    </div>

    <div class="mt-auto pt-5 flex justify-center">
      <BaseButton
        class="min-w-[142px]"
        :title="__('Import workflow', textDomain)"
        variant="secondary"
        :disabled="!available"
        @click="$emit('click')"
      />
    </div>
  </article>
</template>
