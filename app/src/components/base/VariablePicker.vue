<script setup lang="ts">
/**
 * VariablePicker.vue
 *
 * Compact dropdown button that lists the available text variables
 * (placeholders) and emits the chosen one. Reused next to the emoji button in
 * the rich text editor and inside text fields that support placeholder
 * substitution (e.g. the recipient field).
 *
 * @since 2.0.0
 */
import { computed, ref } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { CodeAlt } from '@boxicons/vue';
import { __, textDomain } from '../../utils/i18n';

interface PlaceholderItem {
  placeholder: string;
  description?: string;
}

const props = defineProps({
  placeholders: { type: Array as () => Array<PlaceholderItem | string>, default: () => [] },
  disabled: { type: Boolean, default: false },
  buttonClass: {
    type: String,
    default:
      'inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50',
  },
});

const emit = defineEmits(['select']);

const open = ref(false);
const search = ref('');
const rootRef = ref<HTMLElement | null>(null);

onClickOutside(rootRef, () => {
  open.value = false;
});

const items = computed<PlaceholderItem[]>(() =>
  (Array.isArray(props.placeholders) ? props.placeholders : []).map((item) =>
    typeof item === 'string' ? { placeholder: item } : item
  ).filter((item) => item && item.placeholder)
);

const filteredItems = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) {
    return items.value;
  }

  return items.value.filter((item) =>
    `${item.placeholder} ${item.description || ''}`.toLowerCase().includes(term)
  );
});

function toggle() {
  if (props.disabled) {
    return;
  }

  open.value = !open.value;

  if (open.value) {
    search.value = '';
  }
}

function select(placeholder: string) {
  emit('select', placeholder);
  open.value = false;
}
</script>

<template>
  <div ref="rootRef" class="relative">
    <button
      type="button"
      :class="buttonClass"
      :disabled="disabled"
      :aria-label="__('Insert variable', textDomain)"
      :title="__('Insert variable', textDomain)"
      @mousedown.prevent
      @click="toggle"
    >
      <CodeAlt :width="14" :height="14" />
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-full z-[10000] mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
    >
      <div class="border-b border-slate-100 p-2">
        <input
          v-model="search"
          type="text"
          class="w-full rounded-md border border-slate-200 px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
          :placeholder="__('Search variables...', textDomain)"
        />
      </div>

      <div class="max-h-56 overflow-y-auto py-1">
        <button
          v-for="item in filteredItems"
          :key="item.placeholder"
          type="button"
          class="flex w-full flex-col items-start gap-0.5 px-3 py-1.5 text-left transition-colors hover:bg-primary-50"
          @click="select(item.placeholder)"
        >
          <span class="font-mono text-xs font-semibold text-primary-800">{{ item.placeholder }}</span>
          <span v-if="item.description" class="text-[11px] leading-4 text-slate-500">{{ item.description }}</span>
        </button>

        <p v-if="!filteredItems.length" class="px-3 py-3 text-center text-xs text-slate-400">
          {{ __('No variables available.', textDomain) }}
        </p>
      </div>
    </div>
  </div>
</template>
