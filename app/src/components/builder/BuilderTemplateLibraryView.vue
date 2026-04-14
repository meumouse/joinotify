<script setup>
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
});

defineEmits(['update:search', 'update:category', 'select-template', 'back']);

const searchPlaceholder = computed(() => __('Search workflows', textDomain));
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
      <TemplateGrid :templates="templates" :loading="loading" @select="$emit('select-template', $event)" />
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
