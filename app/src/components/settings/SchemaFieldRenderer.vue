<script setup lang="ts">
import { computed } from 'vue';
import BaseButton from '../base/BaseButton.vue';
import BaseInput from '../base/BaseInput.vue';
import BaseSelect from '../base/BaseSelect.vue';
import BaseSwitch from '../base/BaseSwitch.vue';
import BaseTextarea from '../base/BaseTextarea.vue';
import type { WorkflowFieldCondition, WorkflowFieldSchema } from '../../types/workflowBuilder';

const props = defineProps<{
  field: WorkflowFieldSchema;
  modelValue: unknown;
  rootValue?: Record<string, unknown>;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  (event: 'update:modelValue', value: unknown): void;
}>();

const fieldValue = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

function getRootValue(): Record<string, unknown> {
  return props.rootValue || (typeof props.modelValue === 'object' && props.modelValue && !Array.isArray(props.modelValue)
    ? (props.modelValue as Record<string, unknown>)
    : {});
}

function readValue(path: string, source: Record<string, unknown>) {
  if (!path.includes('.')) {
    return source[path];
  }

  return path.split('.').reduce<unknown>((current, segment) => {
    if (!current || typeof current !== 'object') {
      return undefined;
    }

    return (current as Record<string, unknown>)[segment];
  }, source);
}

function matchesCondition(condition: WorkflowFieldCondition, source: Record<string, unknown>): boolean {
  const value = readValue(condition.key, source);
  const expected = condition.value;
  const operator = condition.operator || 'eq';

  switch (operator) {
    case 'neq':
      return value !== expected;
    case 'in':
      return Array.isArray(expected) ? expected.includes(value as never) : false;
    case 'not_in':
      return Array.isArray(expected) ? !expected.includes(value as never) : true;
    case 'truthy':
      return Boolean(value);
    case 'falsy':
      return !Boolean(value);
    case 'eq':
    default:
      return value === expected;
  }
}

function fieldVisible(field: WorkflowFieldSchema): boolean {
  if (!field.condition || !field.condition.length) {
    return true;
  }

  const source = getRootValue();
  return field.condition.every((condition) => matchesCondition(condition, source));
}

function emitObjectPatch(key: string, value: unknown) {
  const current = props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? { ...(props.modelValue as Record<string, unknown>) }
    : {};

  current[key] = value;
  emit('update:modelValue', current);
}

function emitArrayPatch(value: unknown[]) {
  emit('update:modelValue', value);
}

function addRepeaterItem() {
  const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
  current.push({});
  emitArrayPatch(current);
}

function removeRepeaterItem(index: number) {
  const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
  current.splice(index, 1);
  emitArrayPatch(current);
}

function updateRepeaterItem(index: number, key: string, value: unknown) {
  const current = Array.isArray(props.modelValue) ? [...props.modelValue] : [];
  const item = current[index] && typeof current[index] === 'object' && !Array.isArray(current[index])
    ? { ...(current[index] as Record<string, unknown>) }
    : {};

  item[key] = value;
  current[index] = item;
  emitArrayPatch(current);
}

const repeaterItems = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));

const isGroup = computed(() => props.field.component === 'group');
const isRepeater = computed(() => props.field.component === 'repeater');
const inputType = computed(() => {
  if (props.field.component === 'number') {
    return 'number';
  }
  if (props.field.component === 'date') {
    return 'date';
  }
  if (props.field.component === 'time') {
    return 'time';
  }
  return props.field.componentProps && typeof props.field.componentProps === 'object' && typeof props.field.componentProps.type === 'string'
    ? String(props.field.componentProps.type)
    : 'text';
});
</script>

<template>
  <div v-if="fieldVisible(field)" class="space-y-2">
    <label v-if="!isGroup && !isRepeater" class="flex flex-col gap-1.5">
      <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
        {{ field.label }}
      </span>

      <BaseInput
        v-if="field.component === 'input' || field.component === 'number' || field.component === 'date' || field.component === 'time' || field.component === 'placeholder' || field.component === 'custom'"
        v-model="fieldValue"
        :type="inputType"
        :placeholder="field.placeholder || ''"
        :disabled="disabled"
        v-bind="field.componentProps || {}"
      />

      <BaseTextarea
        v-else-if="field.component === 'textarea' || field.component === 'code'"
        v-model="fieldValue"
        :rows="field.rows || 6"
        :placeholder="field.placeholder || ''"
        :disabled="disabled"
      />

      <BaseSelect
        v-else-if="field.component === 'select'"
        v-model="fieldValue"
        :options="field.options || []"
        :placeholder="field.placeholder || ''"
        :disabled="disabled"
      />

      <BaseSwitch
        v-else-if="field.component === 'switch'"
        v-model="fieldValue"
        :label="field.label"
        :disabled="disabled"
      />
    </label>

    <div v-else-if="isGroup" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
      <div class="mb-4">
        <h4 class="text-sm font-semibold text-slate-900">{{ field.label }}</h4>
        <p v-if="field.description" class="mt-1 text-sm leading-6 text-slate-500">{{ field.description }}</p>
      </div>

      <div class="space-y-4">
        <SchemaFieldRenderer
          v-for="child in field.fields || []"
          :key="child.key"
          :field="child"
          :model-value="(fieldValue as Record<string, unknown>)?.[child.key]"
          :root-value="(fieldValue as Record<string, unknown>) || getRootValue()"
          :disabled="disabled"
          @update:model-value="emitObjectPatch(child.key, $event)"
        />
      </div>
    </div>

    <div v-else-if="isRepeater" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
      <div class="mb-4 flex items-start justify-between gap-4">
        <div>
          <h4 class="text-sm font-semibold text-slate-900">{{ field.label }}</h4>
          <p v-if="field.description" class="mt-1 text-sm leading-6 text-slate-500">{{ field.description }}</p>
        </div>

        <BaseButton
          :title="field.placeholder || 'Add item'"
          variant="secondary"
          size="sm"
          :disabled="disabled"
          @click="addRepeaterItem"
        />
      </div>

      <div class="space-y-4">
        <div
          v-for="(_, index) in repeaterItems"
          :key="`${field.key}-${index}`"
          class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div class="mb-4 flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
              {{ field.label }} {{ index + 1 }}
            </p>

            <button
              type="button"
              class="text-sm font-medium text-rose-600 transition hover:text-rose-700"
              :disabled="disabled"
              @click="removeRepeaterItem(index)"
            >
              Remove
            </button>
          </div>

          <div class="space-y-4">
            <SchemaFieldRenderer
              v-for="child in field.fields || []"
              :key="`${field.key}-${index}-${child.key}`"
              :field="child"
              :model-value="(repeaterItems[index] as Record<string, unknown>)?.[child.key]"
              :root-value="(repeaterItems[index] as Record<string, unknown>) || getRootValue()"
              :disabled="disabled"
              @update:model-value="updateRepeaterItem(index, child.key, $event)"
            />
          </div>
        </div>
      </div>
    </div>

    <p v-if="field.helper" class="text-xs leading-5 text-slate-500">{{ field.helper }}</p>
    <p v-if="field.description && !isGroup && !isRepeater" class="text-xs leading-5 text-slate-500">{{ field.description }}</p>
  </div>
</template>
