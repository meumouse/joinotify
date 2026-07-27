<script setup>
/**
 * TemplateGrid.vue
 *
 * Responsive grid that renders the template library results. Shows skeleton
 * placeholders while loading, an empty-state message when there are no
 * templates, and a TemplateCard for each template otherwise. Forwards the
 * selected template through a `select` event.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../utils/i18n';
import TemplateCard from './TemplateCard.vue';

defineProps({
  templates: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  importingTemplate: { type: String, default: '' },
});

defineEmits(['select']);

/**
 * Placeholder indices used to render template card skeletons while loading.
 *
 * @since 2.0.0
 * @type {number[]}
 */
const skeletonCards = Array.from({ length: 6 }, (_, index) => index);
</script>

<template>
  <div class="mx-auto grid w-full max-w-6xl gap-5 sm:grid-cols-2 lg:grid-cols-3">
    <template v-if="loading">
      <article
        v-for="index in skeletonCards"
        :key="`template-skeleton-${index}`"
        class="flex h-full min-h-[260px] flex-col rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm"
      >
        <div class="flex items-center justify-between">
          <div class="h-11 w-11 animate-pulse rounded-xl bg-slate-100" />
          <div class="h-6 w-24 animate-pulse rounded-full bg-slate-100" />
        </div>
        <div class="mt-5 h-5 w-2/3 animate-pulse rounded-full bg-slate-100" />
        <div class="mt-3 h-4 w-full animate-pulse rounded-full bg-slate-100" />
        <div class="mt-2 h-4 w-11/12 animate-pulse rounded-full bg-slate-100" />
        <div class="mt-auto">
          <div class="mt-5 border-t border-dashed border-slate-200" />
          <div class="mt-4 h-4 w-5/6 animate-pulse rounded-full bg-slate-100" />
          <div class="mt-5 h-11 w-full animate-pulse rounded-xl bg-slate-100" />
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
          :category="template.category"
          :integration="template.integration"
          :icon="template.icon"
          :trigger="template.trigger"
          :available="template.available"
          :importing="!!importingTemplate && importingTemplate === (template.file || template.title)"
          :busy="!!importingTemplate"
          @click="$emit('select', template)"
        />
      </template>
      <div
        v-else
        class="col-span-full flex min-h-[240px] items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500"
      >
        {{ __('No templates available.', textDomain) }}
      </div>
    </template>
  </div>
</template>
