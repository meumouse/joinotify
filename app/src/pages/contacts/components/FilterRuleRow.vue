<script setup>
/**
 * FilterRuleRow.vue — one condition of an audience filter: a custom or native
 * field, a tag, the consent or a free search, with its operator and value.
 * Condition types the builder does not draw are shown read only and kept.
 *
 * Edits the rule object it receives in place (it belongs to the editor's
 * reactive tree).
 *
 * @since 2.5.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import { consentOptions } from '../labels';
import {
  EDITABLE_TYPES,
  nativeFieldType,
  newRule,
  operatorLabel,
  operatorsFor,
  ruleTypeLabel,
  takesValue,
} from '../audienceFilter';
import BaseListboxSelect from '../../../components/base/BaseListboxSelect.vue';

const props = defineProps({
  rule: { type: Object, required: true },
  schema: { type: Object, default: () => ({}) },
  fields: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
});

defineEmits(['remove']);

const inputClass =
  'w-full rounded-[8px] border border-slate-200 bg-white px-3 py-2 text-[14px] text-slate-700 focus:border-primary focus:outline focus:outline-1 focus:-outline-offset-2 focus:outline-primary focus:shadow-none';

const editable = computed(() => EDITABLE_TYPES.includes(props.rule.type));

const typeOptions = computed(() => [
  { label: __('Field', textDomain), value: 'field' },
  { label: __('Tag', textDomain), value: 'tag' },
  { label: __('Consent', textDomain), value: 'consent' },
  { label: __('Search', textDomain), value: 'search' },
]);

const fieldOptions = computed(() => [
  { label: __('— Pick a field —', textDomain), value: '' },
  ...props.fields.map((field) => ({ label: field.label, value: field.key })),
  ...(props.schema.nativeFields || []).map((name) => ({ label: name, value: name })),
]);

const fieldType = computed(() => {
  const custom = props.fields.find((field) => field.key === props.rule.key);

  if (custom) {
    return custom.type;
  }

  return props.rule.key ? nativeFieldType(props.rule.key) : 'text';
});

const customField = computed(() => props.fields.find((field) => field.key === props.rule.key) || null);

const operatorOptions = computed(() => {
  const source = props.rule.type === 'field' ? fieldType.value : props.rule.type;

  return operatorsFor(source, props.schema).map((op) => ({ label: operatorLabel(op), value: op }));
});

const tagOptions = computed(() => [
  { label: __('— Pick a tag —', textDomain), value: '' },
  ...props.tags.map((tag) => ({ label: tag.name, value: tag.id })),
]);

const choiceOptions = computed(() => [
  { label: __('— Pick —', textDomain), value: '' },
  ...(customField.value?.options || []).map((option) => ({ label: option, value: option })),
]);

const inputType = computed(() => ({ number: 'number', date: 'date', datetime: 'date' })[fieldType.value] || 'text');

function setType(type) {
  const fresh = newRule(type, props.schema);

  Object.keys(props.rule).forEach((key) => delete props.rule[key]);
  Object.assign(props.rule, fresh);
}

function setKey(key) {
  props.rule.key = key;

  const ops = operatorOptions.value.map((option) => option.value);

  if (!ops.includes(props.rule.op)) {
    props.rule.op = ops[0];
  }

  props.rule.value = '';
}

function setValue(value) {
  props.rule.value = fieldType.value === 'number' && value !== '' ? Number(value) : value;
}

const summary = computed(() =>
  Object.entries(props.rule)
    .filter(([key]) => key !== 'type')
    .map(([key, value]) => `${key}: ${Array.isArray(value) ? value.join(', ') : String(value)}`)
    .join(' · ')
);
</script>

<template>
  <div class="flex flex-wrap items-start gap-2 rounded-[8px] bg-white p-2 ring-1 ring-slate-200">
    <template v-if="editable">
      <div class="w-32">
        <BaseListboxSelect :model-value="rule.type" :options="typeOptions" @update:model-value="setType" />
      </div>

      <div v-if="rule.type === 'field'" class="w-48">
        <BaseListboxSelect :model-value="rule.key || ''" :options="fieldOptions" @update:model-value="setKey" />
      </div>

      <div class="w-40">
        <BaseListboxSelect v-model="rule.op" :options="operatorOptions" />
      </div>

      <template v-if="takesValue(rule.op)">
        <div v-if="rule.type === 'consent'" class="w-40">
          <BaseListboxSelect v-model="rule.value" :options="consentOptions()" />
        </div>
        <div v-else-if="rule.type === 'tag'" class="w-48">
          <BaseListboxSelect v-model="rule.value" :options="tagOptions" />
        </div>
        <div v-else-if="rule.type === 'field' && (fieldType === 'select' || fieldType === 'multi_select')" class="w-48">
          <BaseListboxSelect :model-value="rule.value || ''" :options="choiceOptions" @update:model-value="setValue" />
        </div>
        <div v-else-if="rule.type === 'field' && fieldType === 'boolean'" class="w-32">
          <BaseListboxSelect
            :model-value="rule.value === true ? 'true' : rule.value === false ? 'false' : ''"
            :options="[{ label: __('Yes', textDomain), value: 'true' }, { label: __('No', textDomain), value: 'false' }]"
            @update:model-value="rule.value = $event === 'true'"
          />
        </div>
        <input
          v-else
          :class="inputClass"
          class="min-w-[160px] flex-1"
          :type="inputType"
          :value="rule.value ?? ''"
          @input="setValue($event.target.value)"
        />
      </template>
    </template>

    <p v-else class="flex-1 px-2 py-2 text-[13px] text-slate-600">
      <span class="font-medium">{{ ruleTypeLabel(rule.type) }}</span>
      <span class="ml-2 text-slate-400">{{ summary }}</span>
      <span class="ml-2 text-[12px] text-slate-400">{{ __('(edit it in the Joinotify panel)', textDomain) }}</span>
    </p>

    <button type="button" class="ml-auto rounded-[8px] px-2 py-2 text-[13px] text-rose-600 hover:bg-rose-50" @click="$emit('remove')">
      {{ __('Remove', textDomain) }}
    </button>
  </div>
</template>
