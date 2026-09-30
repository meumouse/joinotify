<script setup>
/**
 * FilterGroupEditor.vue — a group of an audience filter: "all" or "any" of
 * its conditions, which can hold nested groups up to the platform's three
 * levels. Recursive.
 *
 * Edits the group object it receives in place (it belongs to the editor's
 * reactive tree).
 *
 * @since 2.5.0
 */
import { __, textDomain } from '../../../utils/i18n';
import { MAX_DEPTH, emptyGroup, isGroup, newRule } from '../audienceFilter';
import FilterRuleRow from './FilterRuleRow.vue';

defineOptions({ name: 'FilterGroupEditor' });

const props = defineProps({
  group: { type: Object, required: true },
  depth: { type: Number, default: 1 },
  schema: { type: Object, default: () => ({}) },
  fields: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  canAdd: { type: Boolean, default: true },
});

defineEmits(['remove']);

function addRule() {
  props.group.rules.push(newRule('field', props.schema));
}

function addGroup() {
  const group = emptyGroup(props.group.op === 'and' ? 'or' : 'and');

  group.rules.push(newRule('field', props.schema));
  props.group.rules.push(group);
}

function removeAt(index) {
  props.group.rules.splice(index, 1);
}
</script>

<template>
  <div class="flex flex-col gap-2 rounded-[8px] p-3" :class="depth === 1 ? 'bg-slate-50' : 'bg-slate-100/70 ring-1 ring-slate-200'">
    <div class="flex flex-wrap items-center gap-2 text-[13px] text-slate-600">
      <span>{{ __('Contacts that match', textDomain) }}</span>
      <select v-model="group.op" class="rounded-[8px] border border-slate-200 bg-white px-2 py-1 text-[13px]">
        <option value="and">{{ __('all', textDomain) }}</option>
        <option value="or">{{ __('any', textDomain) }}</option>
      </select>
      <span>{{ __('of these conditions', textDomain) }}</span>
      <button v-if="depth > 1" type="button" class="ml-auto rounded-[8px] px-2 py-1 text-rose-600 hover:bg-rose-50" @click="$emit('remove')">
        {{ __('Remove group', textDomain) }}
      </button>
    </div>

    <template v-for="(rule, index) in group.rules" :key="index">
      <FilterGroupEditor
        v-if="isGroup(rule)"
        :can-add="canAdd"
        :depth="depth + 1"
        :fields="fields"
        :group="rule"
        :schema="schema"
        :tags="tags"
        @remove="removeAt(index)"
      />
      <FilterRuleRow
        v-else
        :fields="fields"
        :rule="rule"
        :schema="schema"
        :tags="tags"
        @remove="removeAt(index)"
      />
    </template>

    <div class="flex flex-wrap gap-2">
      <button
        type="button"
        class="rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-[13px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
        :disabled="!canAdd"
        @click="addRule"
      >
        {{ __('Add condition', textDomain) }}
      </button>
      <button
        v-if="depth < MAX_DEPTH"
        type="button"
        class="rounded-[8px] border border-slate-200 bg-white px-3 py-1.5 text-[13px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
        :disabled="!canAdd"
        @click="addGroup"
      >
        {{ __('Add group', textDomain) }}
      </button>
    </div>
  </div>
</template>
