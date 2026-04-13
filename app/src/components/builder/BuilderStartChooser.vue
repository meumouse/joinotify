<script setup>
import { __, textDomain } from '../../utils/i18n';
import StartOptionCard from './StartOptionCard.vue';

defineProps({
  creating: { type: Boolean, default: false },
});

defineEmits(['scratch', 'template', 'import', 'back']);
</script>

<template>
  <section class="mx-auto flex min-h-full w-full max-w-6xl flex-col items-center justify-center px-4 py-10">
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="text-[32px] font-semibold tracking-tight text-slate-900 sm:text-[34px]">
        {{ __('How would you like to create your new workflow?', textDomain) }}
      </h2>
      <p class="mt-3 text-base leading-7 text-slate-500">
        {{ __('Choose the option you prefer to start building the workflow.', textDomain) }}
      </p>
    </div>

    <div class="mt-14 grid w-full gap-6 lg:grid-cols-3">
      <StartOptionCard
        :title="__('Start from scratch', textDomain)"
        :description="__('Create your automation workflow from scratch.', textDomain)"
        :cta="__('Start from scratch', textDomain)"
        :disabled="creating"
        @click="$emit('scratch')"
      >
        <template #icon>
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 3h10a1 1 0 0 1 1 1v2h-2.5L14 3H10L8.5 6H6V4a1 1 0 0 1 1-1Z" />
            <path d="M6 6h12v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1Z" stroke-dasharray="2 2" />
          </svg>
        </template>
      </StartOptionCard>

      <StartOptionCard
        :title="__('Start with a template', textDomain)"
        :description="__('Create your automation workflow from a template you choose.', textDomain)"
        :cta="__('Use a template', textDomain)"
        :disabled="creating"
        @click="$emit('template')"
      >
        <template #icon>
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 3h10a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path d="M9 8h6M9 12h6M9 16h4" />
            <path d="M9 4V2h6v2" />
          </svg>
        </template>
      </StartOptionCard>

      <StartOptionCard
        :title="__('Import a template', textDomain)"
        :description="__('Create your automation workflow by importing a file.', textDomain)"
        :cta="__('Import file', textDomain)"
        :disabled="creating"
        @click="$emit('import')"
      >
        <template #icon>
          <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 3h6v3h3v15H6V6h3z" />
            <path d="M9 3l3 3 3-3" />
            <path d="M9 12h6" />
            <path d="M10 15l2-2 2 2" />
          </svg>
        </template>
      </StartOptionCard>
    </div>

    <div class="mt-14 flex justify-center">
      <button
        type="button"
        class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-primary-700"
        @click="$emit('back')"
      >
        <span aria-hidden="true">&larr;</span>
        <span>{{ __('Back to dashboard', textDomain) }}</span>
      </button>
    </div>
  </section>
</template>
