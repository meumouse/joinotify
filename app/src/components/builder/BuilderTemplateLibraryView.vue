<script setup>
import BaseButton from '../base/BaseButton.vue';
import BaseCard from '../base/BaseCard.vue';
import BaseSearchInput from '../base/BaseSearchInput.vue';
import TemplateCategorySelect from './TemplateCategorySelect.vue';
import TemplateGrid from './TemplateGrid.vue';
import TemplateLibraryHeader from './TemplateLibraryHeader.vue';

defineProps({
  search: { type: String, default: '' },
  category: { type: String, default: 'all' },
  categoryOptions: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] },
});

defineEmits(['update:search', 'update:category', 'select-template', 'back']);
</script>

<template>
  <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-soft">
    <TemplateLibraryHeader />

    <BaseCard class="mt-6 p-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
        <BaseSearchInput
          class="flex-1"
          :model-value="search"
          placeholder="Search by categories or names"
          @update:model-value="$emit('update:search', $event)"
        />
        <TemplateCategorySelect :model-value="category" :options="categoryOptions" @update:model-value="$emit('update:category', $event)" />
      </div>
    </BaseCard>

    <div class="mt-6">
      <TemplateGrid :templates="templates" @select="$emit('select-template', $event)" />
    </div>

    <div class="mt-6 flex justify-start">
      <button
        type="button"
        class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 transition hover:text-primary-700"
        @click="$emit('back')"
      >
        <span aria-hidden="true">&larr;</span>
        <span>Back</span>
      </button>
    </div>
  </section>
</template>
