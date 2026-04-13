<script setup>
defineProps({
  items: { type: Array, default: () => [] },
  modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      class="rounded-[8px] px-4 py-2 text-sm font-medium transition"
      :class="modelValue === item.id ? 'bg-primary-700 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
      @click="$emit('update:modelValue', item.id)"
    >
      {{ item.label }}
    </button>
  </div>
</template>
