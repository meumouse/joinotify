<script setup>
defineProps({
  tabs: { type: Array, default: () => [] },
  activeStatus: { type: String, default: '' },
});

defineEmits(['select']);
</script>

<template>
  <div class="flex flex-wrap gap-2 rounded-[8px] border border-slate-200 bg-white p-2 shadow-[0_1px_0_rgba(0,0,0,0.02)]">
    <button
      v-for="tab in tabs"
      :key="tab.value"
      type="button"
      class="inline-flex items-center gap-2 rounded-[8px] px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
      :class="tab.value === activeStatus
        ? 'bg-primary-700 text-white shadow-sm'
        : 'text-ink hover:bg-shell-50 hover:text-primary-800'"
      @click="$emit('select', tab.value)"
    >
      <span>{{ tab.label }}</span>
      <span
        class="inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold"
        :class="tab.value === activeStatus ? 'bg-white/10 text-white' : 'bg-primary-50 text-primary-800'"
      >
        {{ tab.count }}
      </span>
    </button>
  </div>
</template>
