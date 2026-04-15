<script setup lang="ts">
defineProps({
  placeholders: { type: Array, default: () => [] },
  title: { type: String, default: 'Placeholders' },
});

defineEmits(['select']);
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
      <h4 class="text-sm font-semibold text-slate-900">{{ title }}</h4>
      <span class="text-xs uppercase tracking-[0.18em] text-slate-400">
        {{ Array.isArray(placeholders) ? placeholders.length : 0 }}
      </span>
    </div>

    <div v-if="Array.isArray(placeholders) && placeholders.length" class="flex flex-wrap gap-2">
      <button
        v-for="placeholder in placeholders"
        :key="placeholder.placeholder || placeholder"
        type="button"
        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800"
        @click="$emit('select', placeholder.placeholder || placeholder)"
      >
        {{ placeholder.placeholder || placeholder }}
      </button>
    </div>

    <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
      No placeholders available.
    </div>
  </div>
</template>
