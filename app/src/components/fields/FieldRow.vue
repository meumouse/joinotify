<script setup>

/**
 * FieldRow.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import FieldControl from './FieldControl.vue';

defineProps({
  modelValue: { type: [String, Number, Boolean], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">{{ field.label || field.name || '' }}</h3>
      <p v-if="field.description" class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
        {{ field.description }}
      </p>
    </div>

    <div class="lg:justify-self-start">
      <FieldControl :field="field" :name="name" :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" />
    </div>
  </div>
</template>
