<script setup>
import BaseButton from '../buttons/button/BaseButton.vue';

defineProps({
  title: { type: String, default: 'No workflows found' },
  description: { type: String, default: 'Create a new workflow to start automating your processes.' },
  actionLabel: { type: String, default: 'Add new workflow' },
  actionHref: { type: String, default: '' },
});
</script>

<template>
  <div class="rounded-[8px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-[0_1px_0_rgba(0,0,0,0.02)]">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[8px] bg-shell-50 text-shell-500">
      <svg viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current stroke-[1.8]">
        <path d="M4 7h16M4 12h16M4 17h10" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
    <h2 class="mt-5 text-xl font-semibold text-ink">{{ title }}</h2>
    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-shell-500">{{ description }}</p>
    <div class="mt-6 flex justify-center">
      <BaseButton :href="actionHref" :title="actionLabel" />
    </div>
  </div>
</template>
