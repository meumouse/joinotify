<script setup>
defineProps({
  sections: { type: Array, default: () => [] },
  activeSectionId: { type: String, default: '' },
});

defineEmits(['select']);

function sectionTabIcon(id) {
  const icons = {
    general:
      '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 8h16M6 12h12M8 16h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    phones:
      '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h10v18H7z" stroke="currentColor" stroke-width="2" /><path d="M9 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    integrations:
      '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v8m0 2v8M7 7l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    about:
      '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 10v6M12 7h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
  };

  return icons[id] || icons.general;
}
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
      <span v-html="sectionTabIcon(section.id)"></span>
      <span>{{ section.title }}</span>
    </button>
  </nav>
</template>
