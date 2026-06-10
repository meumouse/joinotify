<script setup>
import { __, textDomain } from '../../utils/i18n';
import TemplateCard from './TemplateCard.vue';

defineProps({
  templates: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  importingTemplate: { type: String, default: '' },
});

defineEmits(['select']);

const skeletonCards = Array.from({ length: 6 }, (_, index) => index);
</script>

<template>
  <div class="mx-auto grid w-full max-w-5xl gap-4 md:grid-cols-2 xl:grid-cols-3">
    <template v-if="loading">
      <article
        v-for="index in skeletonCards"
        :key="`template-skeleton-${index}`"
        class="flex h-full min-h-[228px] flex-col rounded-[10px] border border-slate-200 bg-white p-5"
      >
        <div class="h-6 w-2/3 animate-pulse rounded-full bg-slate-100" />
        <div class="mt-4 h-4 w-full animate-pulse rounded-full bg-slate-100" />
        <div class="mt-2 h-4 w-11/12 animate-pulse rounded-full bg-slate-100" />
        <div class="mt-6 space-y-2">
          <div class="h-4 w-5/6 animate-pulse rounded-full bg-slate-100" />
          <div class="h-4 w-2/3 animate-pulse rounded-full bg-slate-100" />
        </div>
        <div class="mt-auto flex justify-center pt-5">
          <div class="h-10 w-[142px] animate-pulse rounded-[10px] bg-slate-100" />
        </div>
      </article>
    </template>
    <template v-else>
      <template v-if="templates.length">
        <TemplateCard
          v-for="template in templates"
          :key="template.file || template.title"
          :title="template.title"
          :description="template.description"
          :integration="template.integration"
          :trigger="template.trigger"
          :available="template.available"
          :importing="!!importingTemplate && importingTemplate === (template.file || template.title)"
          :busy="!!importingTemplate"
          @click="$emit('select', template)"
        />
      </template>
      <div
        v-else
        class="col-span-full flex min-h-[240px] items-center justify-center rounded-[10px] border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500"
      >
        {{ __('No templates available.', textDomain) }}
      </div>
    </template>
  </div>
</template>
