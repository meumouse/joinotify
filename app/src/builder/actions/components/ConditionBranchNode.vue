<script setup lang="ts">
defineProps({
  branchKey: { type: String, default: '' },
  branchLabel: { type: String, default: '' },
  emptyLabel: { type: String, default: '' },
  tone: { type: String, default: 'emerald' },
});
</script>

<template>
  <section
    class="rounded-[28px] border p-4 shadow-[0_10px_30px_rgba(15,23,42,0.06)]"
    :class="tone === 'emerald' ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50'"
  >
    <div class="mb-4 flex items-center justify-between gap-3">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.22em]" :class="tone === 'emerald' ? 'text-emerald-700' : 'text-rose-700'">
          {{ branchLabel }}
        </p>
        <p class="mt-1 text-sm" :class="tone === 'emerald' ? 'text-emerald-900/70' : 'text-rose-900/70'">
          {{ emptyLabel || 'Add actions inside this branch.' }}
        </p>
      </div>

      <span class="rounded-full bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em]" :class="tone === 'emerald' ? 'text-emerald-700' : 'text-rose-700'">
        {{ branchKey }}
      </span>
    </div>

    <slot />
  </section>
</template>
