<script setup lang="ts">
import { ref, computed } from 'vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import { useWorkflowBuilderStore } from '../../../stores/useWorkflowBuilderStore';
import { createApiClient } from '../../../utils/api';
import { __, textDomain } from '../../../utils/i18n';

interface ProductItem {
  id: number;
  title: string;
}

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const store = useWorkflowBuilderStore();
const api = createApiClient(store.bootstrap);

const search = ref('');
const results = ref<ProductItem[]>([]);
const loading = ref(false);
const errorMsg = ref('');

const selected = computed<ProductItem[]>(() =>
  Array.isArray(props.modelValue)
    ? (props.modelValue as Array<Record<string, unknown>>).map((p) => ({
        id: Number(p.id),
        title: String(p.title || p.product_title || p.id),
      }))
    : [],
);

let debounceTimer: ReturnType<typeof setTimeout> | undefined;

function onSearchInput(value: unknown) {
  search.value = String(value ?? '');

  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }

  if (search.value.trim().length < 2) {
    results.value = [];
    return;
  }

  debounceTimer = setTimeout(runSearch, 350);
}

async function runSearch() {
  if (!store.bootstrap?.rest?.root) {
    errorMsg.value = __('Product search is unavailable.', textDomain);
    return;
  }

  loading.value = true;
  errorMsg.value = '';

  try {
    const res = await api.get(`admin/builder/woo-products?search=${encodeURIComponent(search.value.trim())}`);
    const list = Array.isArray((res as Record<string, unknown>)?.products) ? (res as Record<string, unknown>).products as Array<Record<string, unknown>> : [];
    results.value = list.map((p) => ({ id: Number(p.id), title: String(p.product_title || p.title || p.id) }));
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
    results.value = [];
  } finally {
    loading.value = false;
  }
}

function isSelected(id: number) {
  return selected.value.some((p) => p.id === id);
}

function addProduct(item: ProductItem) {
  if (isSelected(item.id)) {
    return;
  }

  emit('update:modelValue', [...selected.value, item]);
}

function removeProduct(id: number) {
  emit('update:modelValue', selected.value.filter((p) => p.id !== id));
}
</script>

<template>
  <div class="space-y-2">
    <BaseTextField
      :model-value="search"
      :label="__('Search products', textDomain)"
      :placeholder="__('Type at least 2 characters…', textDomain)"
      @update:model-value="onSearchInput($event)"
    />

    <p v-if="loading" class="text-xs text-slate-500">{{ __('Searching…', textDomain) }}</p>
    <p v-else-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>

    <ul v-if="results.length" class="max-h-40 divide-y divide-slate-100 overflow-auto rounded-md border border-slate-200">
      <li
        v-for="item in results"
        :key="item.id"
        class="flex items-center justify-between px-3 py-2 text-sm"
      >
        <span class="truncate">{{ item.title }} <span class="text-slate-400">#{{ item.id }}</span></span>
        <button
          type="button"
          class="text-xs text-indigo-600 hover:underline disabled:opacity-40"
          :disabled="isSelected(item.id)"
          @click="addProduct(item)"
        >
          {{ isSelected(item.id) ? __('Added', textDomain) : __('Add', textDomain) }}
        </button>
      </li>
    </ul>

    <div v-if="selected.length" class="flex flex-wrap gap-2">
      <span
        v-for="item in selected"
        :key="item.id"
        class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700"
      >
        {{ item.title }} <span class="text-slate-400">#{{ item.id }}</span>
        <button type="button" class="text-slate-400 hover:text-rose-600" :aria-label="__('Remove', textDomain)" @click="removeProduct(item.id)">×</button>
      </span>
    </div>
    <p v-else class="text-xs text-slate-500">{{ __('No products selected yet.', textDomain) }}</p>
  </div>
</template>
