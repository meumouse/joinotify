<script setup>
/**
 * BuilderTemplateLibraryView.vue
 *
 * Full-page template library screen shown when starting a workflow from a
 * template. Composes the header, search input, category filter (dropdown +
 * quick pills) and template grid, and relays user interactions (search/category
 * changes, template selection, back navigation) to the parent via emitted
 * events. Fully responsive from a single-column mobile layout up to a
 * three-column desktop grid.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseSearchInput from '../base/BaseSearchInput.vue';
import TemplateCategorySelect from './TemplateCategorySelect.vue';
import TemplateGrid from './TemplateGrid.vue';
import TemplateLibraryHeader from './TemplateLibraryHeader.vue';

const props = defineProps({
  search: { type: String, default: '' },
  category: { type: String, default: 'all' },
  categoryOptions: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  importingTemplate: { type: String, default: '' },
});

defineEmits(['update:search', 'update:category', 'select-template', 'back']);

/**
 * Localized placeholder for the workflow search input.
 *
 * @since 2.0.0
 * @returns {string} Translated "Search workflows" placeholder text.
 */
const searchPlaceholder = computed(() => __('Search workflows', textDomain));

/**
 * Localized label for the back navigation button.
 *
 * @since 2.0.0
 * @returns {string} Translated "Back" label text.
 */
const backLabel = computed(() => __('Back', textDomain));

/**
 * Quick-filter pills derived from the category options.
 *
 * Mirrors the dropdown selection so users can switch categories with a single
 * tap. The catch-all "all" option is relabeled to a compact "All" pill.
 *
 * @since 2.0.0
 * @returns {Array<{label: string, value: string}>} Pill descriptors.
 */
const categoryPills = computed(() =>
  props.categoryOptions.map((option) => ({
    value: option.value,
    label: option.value === 'all' ? __('All', textDomain) : option.label,
  })),
);
</script>

<template>
  <section class="min-h-full w-full bg-slate-50/70">
    <div class="mx-auto flex w-full max-w-[1200px] flex-col px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
      <TemplateLibraryHeader class="w-full" />

      <div class="mx-auto mt-8 w-full max-w-3xl sm:mt-10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
          <BaseSearchInput
            class="flex-1"
            :model-value="search"
            :placeholder="searchPlaceholder"
            @update:model-value="$emit('update:search', $event)"
          />
          <TemplateCategorySelect
            class="w-full sm:w-[260px]"
            :model-value="category"
            :options="categoryOptions"
            @update:model-value="$emit('update:category', $event)"
          />
        </div>

        <div
          v-if="categoryPills.length > 1"
          class="mt-5 flex flex-wrap items-center justify-center gap-2"
        >
          <button
            v-for="pill in categoryPills"
            :key="pill.value"
            type="button"
            class="rounded-full px-4 py-2 text-sm font-medium transition"
            :class="category === pill.value
              ? 'bg-slate-900 text-white shadow-sm'
              : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900'"
            @click="$emit('update:category', pill.value)"
          >
            {{ pill.label }}
          </button>
        </div>
      </div>

      <div class="mt-10 sm:mt-12">
        <TemplateGrid
          :templates="templates"
          :loading="loading"
          :importing-template="importingTemplate"
          @select="$emit('select-template', $event)"
        />
      </div>

      <div class="mt-12 flex justify-center">
        <button
          type="button"
          class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 transition hover:text-primary-700"
          @click="$emit('back')"
        >
          <span aria-hidden="true">&larr;</span>
          <span>{{ backLabel }}</span>
        </button>
      </div>
    </div>
  </section>
</template>
