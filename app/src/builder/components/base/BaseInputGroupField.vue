<script setup lang="ts">
/**
 * BaseInputGroupField.vue
 *
 * Labelled text input that can display a static prefix and/or suffix affixed to
 * the control (e.g. units or currency symbols). Supports v-model and emits input
 * and change events for use in builder settings.
 *
 * @since 2.0.0
 */
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

/**
 * Resolve the input element id, generating a random fallback when no id prop is
 * supplied so the label stays associated with its control.
 *
 * @since 2.0.0
 * @returns {string} The id to apply to the input element.
 */
const inputId = computed(() => props.id || `input-group-${Math.random().toString(36).slice(2, 10)}`);

/**
 * Handle input events, syncing the v-model and emitting the input event.
 *
 * @since 2.0.0
 * @param {Event} event Native input event from the field.
 */
function handleInput(event: Event) {
  const target = event.target as HTMLInputElement;
  emit('update:modelValue', target.value);
  emit('input', target.value);
}

/**
 * Handle the change event, forwarding the committed value.
 *
 * @since 2.0.0
 * @param {Event} event Native change event from the field.
 */
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
