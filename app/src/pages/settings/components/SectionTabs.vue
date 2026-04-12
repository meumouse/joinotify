<script setup>

/**
 * SectionTabs.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import AppIcon from '../../../components/icons/AppIcon.vue';
import { getSettingsSectionIcon } from '../../../config/settingsSections';

defineProps({
  sections: { type: Array, default: () => [] },
  activeSectionId: { type: String, default: '' },
});

defineEmits(['select']);
</script>

<template>
  <nav class="mt-10 flex w-fit overflow-hidden rounded-[8px] bg-[#e7edf5] p-0.5">
    <button
      v-for="section in sections"
      :key="section.id"
      type="button"
      class="flex min-w-[165px] items-center justify-center gap-2 rounded-none px-6 py-5 text-[15px] font-semibold uppercase tracking-wide transition first:rounded-l-[8px] last:rounded-r-[8px]"
      :class="activeSectionId === section.id ? 'bg-primary-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-800'"
      @click="$emit('select', section.id)"
    >
      <AppIcon :name="getSettingsSectionIcon(section.id)" class="h-[22px] w-[22px] shrink-0" />
      <span>{{ section.title }}</span>
    </button>
  </nav>
</template>
