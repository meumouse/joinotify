<script setup>
import BaseButton from '../buttons/button/BaseButton.vue';
import BrandMark from '../brand/BrandMark.vue';

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  actionHref: { type: String, default: '' },
  actionDisabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.22em] text-shell-500">Joinotify</p>
      <div class="flex items-center gap-3">
        <BrandMark size="md" variant="primary" />
        <h1 class="text-3xl font-semibold tracking-tight text-ink">{{ title }}</h1>
      </div>
      <p v-if="description" class="max-w-3xl text-sm leading-6 text-shell-500">
        {{ description }}
      </p>
    </div>

    <BaseButton
      v-if="actionLabel"
      :disabled="actionDisabled"
      :href="actionHref"
      :loading="loading"
      :title="actionLabel"
      variant="primary"
    />
  </header>
</template>
