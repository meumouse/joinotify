<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../base/BaseButton.vue';

defineProps({
  disabled: { type: Boolean, default: false },
});

defineEmits(['continue', 'back']);
</script>

<template>
  <div class="flex items-center justify-between gap-3">
    <button
      type="button"
      class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 transition hover:text-primary-700"
      @click="$emit('back')"
    >
      <span aria-hidden="true">&larr;</span>
      <span>{{ __('Back', textDomain) }}</span>
    </button>
    <BaseButton :title="__('Continue', textDomain)" :disabled="disabled" @click="$emit('continue')" />
  </div>
</template>
