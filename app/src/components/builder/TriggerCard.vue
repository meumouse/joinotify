<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  selected: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="rounded-lg border p-4 text-left transition"
    :class="selected ? 'border-primary-200 bg-primary-50 text-primary-950 shadow-soft' : 'border-slate-200 bg-white hover:border-primary-200 hover:shadow-soft'"
    @click="$emit('click')"
  >
    <h3 class="text-sm font-semibold">{{ title }}</h3>
    <p class="mt-2 text-sm leading-6" :class="selected ? 'text-primary-700' : 'text-slate-500'">{{ description }}</p>
  </button>
</template>
