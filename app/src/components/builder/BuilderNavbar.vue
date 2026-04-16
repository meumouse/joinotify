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
            <button type="button" class="block w-full rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('edit-title'); closeMenu()">
              {{ __('Edit workflow title', textDomain) }}
            </button>
            <button type="button" class="block w-full rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('export'); closeMenu()">
              {{ __('Export workflow', textDomain) }}
            </button>
            <button type="button" class="block w-full rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('new'); closeMenu()">
              {{ __('Create a new workflow', textDomain) }}
            </button>
            <button type="button" class="block w-full rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('back'); closeMenu()">
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
