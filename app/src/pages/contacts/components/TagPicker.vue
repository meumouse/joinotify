<script setup>
/**
 * TagPicker.vue — chip input that picks contact tags by id.
 *
 * The selected tags show as removable chips; typing filters the account's
 * tags, Enter picks the first match and Backspace on an empty input drops the
 * last chip.
 *
 * @since 2.5.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../../../utils/i18n';
import TagChip from './TagChip.vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  max: { type: Number, default: 50 },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);

const selected = computed(() =>
  props.modelValue.map((id) => props.tags.find((tag) => tag.id === id) || { id, name: id, color: '' })
);

const suggestions = computed(() => {
  const term = query.value.trim().toLowerCase();

  return props.tags
    .filter((tag) => !props.modelValue.includes(tag.id))
    .filter((tag) => !term || tag.name.toLowerCase().includes(term))
    .slice(0, 30);
});

function add(tag) {
  if (!tag || props.modelValue.length >= props.max) {
    return;
  }

  emit('update:modelValue', [...props.modelValue, tag.id]);
  query.value = '';
}

function remove(id) {
  emit('update:modelValue', props.modelValue.filter((value) => value !== id));
}

function onEnter() {
  add(suggestions.value[0]);
}

function onBackspace() {
  if (!query.value && props.modelValue.length) {
    remove(props.modelValue[props.modelValue.length - 1]);
  }
}

function close() {
  // Let a click on a suggestion land before the list goes away.
  window.setTimeout(() => {
    open.value = false;
  }, 150);
}
</script>

<template>
  <div class="relative">
    <div
      class="flex min-h-[42px] flex-wrap items-center gap-1.5 rounded-[8px] border border-slate-200 bg-white px-2 py-1.5"
      :class="disabled ? 'opacity-60' : ''"
    >
      <TagChip
        v-for="tag in selected"
        :key="tag.id"
        :color="tag.color || ''"
        :name="tag.name"
        :removable="!disabled"
        @remove="remove(tag.id)"
      />
      <input
        v-model="query"
        type="text"
        class="min-w-[120px] flex-1 border-0 bg-transparent px-1 py-1 text-[14px] text-slate-700 shadow-none outline-none focus:shadow-none focus:outline-none"
        :disabled="disabled"
        :placeholder="selected.length ? '' : placeholder || __('Pick tags…', textDomain)"
        @blur="close"
        @focus="open = true"
        @keydown.backspace="onBackspace"
        @keydown.enter.prevent="onEnter"
      />
    </div>

    <ul
      v-if="open && !disabled && suggestions.length"
      class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-[8px] bg-white py-1 text-[14px] shadow-soft ring-1 ring-slate-200"
    >
      <li v-for="tag in suggestions" :key="tag.id">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-slate-50"
          @mousedown.prevent="add(tag)"
        >
          <TagChip :color="tag.color || ''" :name="tag.name" />
          <span v-if="tag.contactCount !== undefined" class="text-[12px] text-slate-400">{{ tag.contactCount }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
