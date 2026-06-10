<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';

defineProps({
  disabled: { type: Boolean, default: false },
  continuing: { type: Boolean, default: false },
});

defineEmits(['continue', 'back']);
</script>

<template>
  <div class="flex items-center justify-between gap-4">
    <button
      type="button"
      class="inline-flex items-center gap-2 text-[15px] font-medium text-slate-500 transition hover:text-slate-900"
      @click="$emit('back')"
    >
      <span aria-hidden="true">&larr;</span>
      <span>{{ __('Back', textDomain) }}</span>
    </button>
    <BaseButton :title="__('Continue', textDomain)" size="md" :disabled="disabled || continuing" :loading="continuing" @click="$emit('continue')" />
  </div>
</template>
