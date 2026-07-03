<script setup>

/**
 * TextAreaField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  showHeader: { type: Boolean, default: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <label class="block">
    <template v-if="showHeader">
      <span class="text-sm font-medium text-ink">{{ field.label }}</span>
      <p v-if="field.description" class="mt-1 text-sm leading-6 text-muted">{{ field.description }}</p>
    </template>
    <textarea
      :name="name"
      :rows="field.rows || 4"
      :placeholder="field.placeholder || ''"
      class="w-full resize-y rounded-lg border border-slate-200 bg-white px-4 py-3 text-[14px] leading-6 text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-100"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    />
  </label>
</template>
