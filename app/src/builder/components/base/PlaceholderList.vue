<script setup lang="ts">
/**
 * PlaceholderList.vue
 *
 * Displays the available message placeholders as clickable chips and emits a
 * select event with the chosen placeholder token, letting the user insert it
 * into a field. Shows an empty state when no placeholders are provided.
 *
 * @since 2.0.0
 */
import { __, textDomain } from '../../../utils/i18n';

defineProps({
  placeholders: { type: Array, default: () => [] },
  title: { type: String, default: () => __('Placeholders', textDomain) },
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
      {{ __('No placeholders available.', textDomain) }}
    </div>
  </div>
</template>
