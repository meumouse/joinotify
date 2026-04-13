<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseButton from '../buttons/button/BaseButton.vue';

defineProps({
  currentPage: { type: Number, default: 1 },
  totalPages: { type: Number, default: 1 },
  totalItems: { type: Number, default: 0 },
  summary: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

defineEmits(['first', 'previous', 'next', 'last']);
</script>

<template>
  <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <p class="text-sm text-shell-500">
      <span class="font-medium text-ink">{{ summary || `${totalItems} ${__('items', textDomain)}` }}</span>
      <span class="mx-2 text-shell-300">|</span>
      {{ __('Page', textDomain) }} {{ currentPage }} {{ __('of', textDomain) }} {{ totalPages }}
    </p>

    <div class="flex flex-wrap items-center gap-2">
      <BaseButton :disabled="disabled || currentPage <= 1" :title="__('First', textDomain)" variant="secondary" @click="$emit('first')" />
      <BaseButton :disabled="disabled || currentPage <= 1" :title="__('Previous', textDomain)" variant="secondary" @click="$emit('previous')" />
      <BaseButton :disabled="disabled || currentPage >= totalPages" :title="__('Next', textDomain)" variant="secondary" @click="$emit('next')" />
      <BaseButton :disabled="disabled || currentPage >= totalPages" :title="__('Last', textDomain)" variant="secondary" @click="$emit('last')" />
    </div>
  </div>
</template>
