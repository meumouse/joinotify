<script setup>
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
      <span class="font-medium text-ink">{{ summary || `${totalItems} items` }}</span>
      <span class="mx-2 text-shell-300">|</span>
      Page {{ currentPage }} of {{ totalPages }}
    </p>

    <div class="flex flex-wrap items-center gap-2">
      <BaseButton :disabled="disabled || currentPage <= 1" title="First" variant="secondary" @click="$emit('first')" />
      <BaseButton :disabled="disabled || currentPage <= 1" title="Previous" variant="secondary" @click="$emit('previous')" />
      <BaseButton :disabled="disabled || currentPage >= totalPages" title="Next" variant="secondary" @click="$emit('next')" />
      <BaseButton :disabled="disabled || currentPage >= totalPages" title="Last" variant="secondary" @click="$emit('last')" />
    </div>
  </div>
</template>
