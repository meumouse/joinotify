<script setup>
/**
 * BuilderStartChooser.vue
 *
 * Landing screen that lets the user pick how to create a new workflow: from
 * scratch, from a template, by importing a file, or with AI. Each option card
 * emits the corresponding event to start that creation flow.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
import StartOptionCard from './StartOptionCard.vue';
import { ArrowLeftStroke, ArrowToTopStroke, ClipboardDetail, FilePlus, Sparkles } from '@boxicons/vue';

defineProps({
  creating: { type: Boolean, default: false },
});

defineEmits(['scratch', 'template', 'import', 'ai', 'back']);
</script>

<template>
  <section class="mx-auto flex min-h-full w-full max-w-7xl flex-col items-center justify-center px-4 py-10 sm:px-6 lg:px-8 2xl:max-w-[88rem]">
    <div class="mx-auto max-w-2xl text-center">
      <h2 class="text-[32px] font-semibold tracking-tight text-slate-900 sm:text-[34px]">
        {{ __('How would you like to create your new workflow?', textDomain) }}
      </h2>
      <p class="mt-3 text-base leading-7 text-slate-500">
        {{ __('Choose the option you prefer to start building the workflow.', textDomain) }}
      </p>
    </div>

    <div class="mt-14 grid w-full gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4 lg:gap-8">
      <StartOptionCard
        :title="__('Start from scratch', textDomain)"
        :description="__('Create your automation workflow from scratch.', textDomain)"
        :cta="__('Start from scratch', textDomain)"
        :disabled="creating"
        @click="$emit('scratch')"
      >
        <template #icon>
          <FilePlus class="h-7 w-7" aria-hidden="true" />
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
          <ClipboardDetail class="h-7 w-7" aria-hidden="true" />
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
          <ArrowToTopStroke class="h-7 w-7" aria-hidden="true" />
        </template>
      </StartOptionCard>

      <StartOptionCard
        :title="__('Create with AI', textDomain)"
        :description="__('Describe what you want and let AI build the workflow for you.', textDomain)"
        :cta="__('Generate with AI', textDomain)"
        :disabled="creating"
        @click="$emit('ai')"
      >
        <template #icon>
          <Sparkles class="h-7 w-7" aria-hidden="true" />
        </template>
      </StartOptionCard>
    </div>

    <div class="mt-14 flex justify-center">
      <button
        type="button"
        class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-primary-700"
        @click="$emit('back')"
      >
        <ArrowLeftStroke class="h-4 w-4" aria-hidden="true" />
        <span>{{ __('Back to dashboard', textDomain) }}</span>
      </button>
    </div>
  </section>
</template>
