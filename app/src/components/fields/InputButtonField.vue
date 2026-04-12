<script setup>

/**
 * InputButtonField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed, useSlots } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  name: { type: String, required: true },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const slots = useSlots();

const hasActions = computed(() => Boolean(slots.actions));

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <label class="block">
    <span v-if="label" class="text-sm font-medium text-ink">{{ label }}</span>
    <p v-if="description" class="mt-1 text-sm leading-6 text-muted">
      {{ description }}
    </p>

    <div class="mt-2 flex overflow-hidden rounded-[10px] border border-slate-200 bg-white transition focus-within:border-primary-700 focus-within:ring-4 focus-within:ring-primary-100">
      <input
        :id="name"
        :name="name"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        class="min-w-0 flex-1 bg-transparent px-4 py-3 text-[14px] text-slate-700 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-50"
      />

      <div
        v-if="hasActions"
        class="flex shrink-0 items-stretch border-l border-slate-200 bg-slate-50"
      >
        <slot name="actions" />
      </div>
    </div>
  </label>
</template>
