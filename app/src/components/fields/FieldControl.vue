<script setup>
import { computed } from 'vue';
import ToggleSwitch from '../toggles/ToggleSwitch.vue';
import TextField from './TextField.vue';
import TextAreaField from './TextAreaField.vue';
import SelectField from './SelectField.vue';
import PhoneField from './PhoneField.vue';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const fieldComponent = computed(() => {
  const map = {
    text: TextField,
    textarea: TextAreaField,
    select: SelectField,
    phone: PhoneField,
  };

  return map[props.field.type] || TextField;
});

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <ToggleSwitch
    v-if="field.type === 'toggle'"
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
    :field="field"
    :name="name"
  />
</template>
