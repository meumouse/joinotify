<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseSwitch from '../base/BaseSwitch.vue';
import BrandMark from '../brand/BrandMark.vue';

const props = defineProps({
  title: { type: String, default: '' },
  status: { type: String, default: 'draft' },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  statusLoading: { type: Boolean, default: false },
  docsUrl: { type: String, default: '#' },
});

defineEmits(['update:status', 'test', 'new', 'back', 'export', 'edit-title', 'save']);

const menuOpen = ref(false);

function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}

function closeMenu() {
  menuOpen.value = false;
}

function handleWindowClick(event) {
  if (!event.target.closest?.('[data-builder-menu]')) {
    closeMenu();
  }
}

function badgeClass() {
  return props.status === 'publish'
    ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
    : 'border-slate-200 bg-slate-50 text-slate-600';
}

onMounted(() => {
  window.addEventListener('click', handleWindowClick);
});

onBeforeUnmount(() => {
  window.removeEventListener('click', handleWindowClick);
});
</script>

<template>
  <header class="sticky top-0 z-10 border-b border-slate-200 bg-white">
    <div class="flex h-[80px] w-full items-stretch">
      <div class="flex w-[88px] shrink-0 items-center justify-center border-r border-slate-200">
        <BrandMark variant="primary" size="md" />
      </div>

      <div class="flex min-w-0 flex-1 items-center gap-3 px-8">
        <button
          type="button"
          class="max-w-[28rem] truncate text-left text-[1.1rem] font-semibold tracking-tight text-slate-900 hover:text-primary-700"
          @click="$emit('edit-title')"
        >
          {{ title || __('New workflow', textDomain) }}
        </button>

        <BaseBadge :class="badgeClass()" class="rounded-full px-3 py-1 text-xs font-medium">
          {{ status === 'publish' ? __('Active workflow', textDomain) : __('Inactive workflow', textDomain) }}
        </BaseBadge>
      </div>

      <div class="ml-auto flex items-center gap-8 pr-6">
        <BaseButton :title="__('Save flow', textDomain)" variant="primary" :loading="saving" @click="$emit('save')" />
        <BaseButton :title="__('Run test', textDomain)" variant="secondary" :loading="loading" @click="$emit('test')" />
        <BaseSwitch
          :model-value="status === 'publish'"
          :label="statusLoading ? __('Saving...', textDomain) : (status === 'publish' ? __('Active', textDomain) : __('Inactive', textDomain))"
          :loading="statusLoading"
          @change="$emit('update:status', $event ? 'publish' : 'draft')"
        />

        <div class="relative" data-builder-menu>
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-2xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            :aria-label="__('Actions menu', textDomain)"
            @click="toggleMenu"
          >
            ...
          </button>
          <div
            v-if="menuOpen"
            class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-soft"
          >
            <button type="button" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('edit-title'); closeMenu()">
              <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 20h9" />
                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
              </svg>
              {{ __('Edit workflow title', textDomain) }}
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('export'); closeMenu()">
              <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <path d="M7 10l5 5 5-5" />
                <path d="M12 15V3" />
              </svg>
              {{ __('Export workflow', textDomain) }}
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('new'); closeMenu()">
              <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                <path d="M14 2v6h6" />
                <path d="M12 12v6M9 15h6" />
              </svg>
              {{ __('Create a new workflow', textDomain) }}
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('back'); closeMenu()">
              <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
              </svg>
              {{ __('Back to dashboard', textDomain) }}
            </button>
          </div>
        </div>

        <a
          :href="docsUrl"
          target="_blank"
          rel="noreferrer"
          class="inline-flex h-10 w-10 items-center justify-center rounded-full text-xl font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
          :aria-label="__('Open documentation', textDomain)"
        >
          ?
        </a>
      </div>
    </div>
  </header>
</template>
