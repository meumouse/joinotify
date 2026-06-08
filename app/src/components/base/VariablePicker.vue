<script setup lang="ts">
/**
 * VariablePicker.vue
 *
 * Button that opens a large, searchable modal listing the available text
 * variables (placeholders) and emits the chosen one. Reused next to the emoji
 * button in the rich text editor and inside text fields that support
 * placeholder substitution (e.g. the recipient field).
 *
 * @since 2.0.0
 */
import { computed, ref } from 'vue';
import { CodeAlt } from '@boxicons/vue';
import ModalDialog from '../modals/ModalDialog.vue';
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

const items = computed<PlaceholderItem[]>(() =>
  (Array.isArray(props.placeholders) ? props.placeholders : [])
    .map((item) => (typeof item === 'string' ? { placeholder: item } : item))
    .filter((item) => item && item.placeholder)
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

function openModal() {
  if (props.disabled) {
    return;
  }

  search.value = '';
  open.value = true;
}

function closeModal() {
  open.value = false;
}

function select(placeholder: string) {
  emit('select', placeholder);
  open.value = false;
}
</script>

<template>
  <div class="inline-flex">
    <button
      type="button"
      :class="buttonClass"
      :disabled="disabled"
      :aria-label="__('Insert variable', textDomain)"
      :title="__('Insert variable', textDomain)"
      @mousedown.prevent
      @click="openModal"
    >
      <CodeAlt :width="14" :height="14" />
    </button>

    <ModalDialog
      :open="open"
      :title="__('Insert a text variable', textDomain)"
      :description="__('Variables are replaced with real data when the message is sent. Click one to insert it.', textDomain)"
      size-class="max-w-3xl"
      @close="closeModal"
    >
      <div class="space-y-4">
        <input
          v-model="search"
          type="text"
          class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10"
          :placeholder="__('Search variables...', textDomain)"
        />

        <div v-if="filteredItems.length" class="grid max-h-[55vh] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
          <button
            v-for="item in filteredItems"
            :key="item.placeholder"
            type="button"
            class="flex flex-col items-start gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-primary-300 hover:bg-primary-50"
            @click="select(item.placeholder)"
          >
            <span class="font-mono text-sm font-semibold text-primary-800">{{ item.placeholder }}</span>
            <span v-if="item.description" class="text-xs leading-5 text-slate-500">{{ item.description }}</span>
          </button>
        </div>

        <div
          v-else
          class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500"
        >
          {{ __('No variables available.', textDomain) }}
        </div>
      </div>
    </ModalDialog>
  </div>
</template>
