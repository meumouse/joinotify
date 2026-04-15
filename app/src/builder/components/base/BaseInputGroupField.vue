<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

const inputId = computed(() => props.id || `input-group-${Math.random().toString(36).slice(2, 10)}`);

function handleInput(event: Event) {
  const target = event.target as HTMLInputElement;
  emit('update:modelValue', target.value);
  emit('input', target.value);
}

function handleChange(event: Event) {
  const target = event.target as HTMLInputElement;
  emit('change', target.value);
}
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>
    <div class="flex overflow-hidden rounded-lg border border-slate-200 bg-white">
      <span v-if="prefix" class="flex items-center border-r border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">
        {{ prefix }}
      </span>
      <input
        :id="inputId"
        :name="name"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        class="joinotify-input-group__control w-full px-4 py-3 text-sm text-slate-900 outline-none"
        @input="handleInput"
        @change="handleChange"
      />
      <span v-if="suffix" class="flex items-center border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">
        {{ suffix }}
      </span>
    </div>
  </label>
</template>
