<script setup>

/**
 * FieldControl.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import ToggleSwitch from '../toggles/ToggleSwitch.vue';
import TextField from './TextField.vue';
import { buildFieldProps, resolveFieldComponent } from './fieldRegistry';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const fieldComponent = computed(() => {
  return resolveFieldComponent(props.field) || TextField;
});

const fieldProps = computed(() => {
  return buildFieldProps(props.field, { showHeader: false });
});

const usesToggle = computed(() => {
  return String(props.field?.type || '').toLowerCase() === 'toggle' && !props.field?.component;
});

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <ToggleSwitch
    v-if="usesToggle"
    :id="name"
    :name="name"
    :aria-label="field.label"
    size="md"
    true-value="yes"
    false-value="no"
    v-model="model"
  />

  <component
    v-else
    :is="fieldComponent"
    v-model="model"
    v-bind="fieldProps"
    :field="field"
    :name="name"
  />
</template>
