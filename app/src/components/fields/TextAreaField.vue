<template>
  <label class="block">
    <span class="text-sm font-medium text-ink">{{ field.label }}</span>
    <p v-if="field.description" class="mt-1 text-sm leading-6 text-muted">{{ field.description }}</p>
    <textarea
      :name="name"
      :rows="field.rows || 4"
      :placeholder="field.placeholder || ''"
      class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    />
  </label>
</template>

<script setup>
defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>
