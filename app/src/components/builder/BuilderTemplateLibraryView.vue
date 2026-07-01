<script setup>
/**
 * BuilderTemplateLibraryView.vue
 *
 * Full-page template library screen shown when starting a workflow from a
 * template. Composes the header, search input, category filter and template
 * grid, and relays user interactions (search/category changes, template
 * selection, back navigation) to the parent via emitted events.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseSearchInput from '../base/BaseSearchInput.vue';
import TemplateCategorySelect from './TemplateCategorySelect.vue';
import TemplateGrid from './TemplateGrid.vue';
import TemplateLibraryHeader from './TemplateLibraryHeader.vue';

defineProps({
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
</script>

<template>
  <section class="mx-auto flex min-h-full w-full max-w-[1200px] flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-4xl flex-col items-center text-center">
      <TemplateLibraryHeader class="w-full" />

      <div class="mt-10 w-full">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-stretch">
          <BaseSearchInput
            class="flex-1"
            :model-value="search"
            :placeholder="searchPlaceholder"
            @update:model-value="$emit('update:search', $event)"
          />
          <TemplateCategorySelect
            class="w-full lg:w-[320px]"
            :model-value="category"
            :options="categoryOptions"
            @update:model-value="$emit('update:category', $event)"
          />
        </div>
      </div>
    </div>

    <div class="mt-12">
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
  </section>
</template>
