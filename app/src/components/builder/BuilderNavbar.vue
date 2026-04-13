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
  docsUrl: { type: String, default: '#' },
});

defineEmits(['update:status', 'test', 'new', 'back', 'export', 'edit-title']);

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
    ? 'border-primary-200 bg-primary-50 text-primary-700'
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
  <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
    <div class="mx-auto flex w-full max-w-[1680px] items-center gap-4 px-4 py-3 lg:px-6">
      <div class="flex min-w-0 items-center gap-4">
        <BrandMark variant="primary" size="md" />
        <div class="min-w-0">
          <button
            type="button"
            class="block max-w-[24rem] truncate text-left text-base font-semibold tracking-tight text-slate-900 hover:text-primary-700"
            @click="$emit('edit-title')"
          >
            {{ title || __('New workflow', textDomain) }}
          </button>
          <BaseBadge class="mt-2" :class="badgeClass()">
            {{ status === 'publish' ? __('Active', textDomain) : __('Inactive', textDomain) }}
          </BaseBadge>
        </div>
      </div>

      <div class="ml-auto flex flex-wrap items-center gap-2">
        <BaseButton :title="__('Run test', textDomain)" variant="secondary" :loading="loading" @click="$emit('test')" />
        <BaseSwitch
          :model-value="status === 'publish'"
          :label="status === 'publish' ? __('Active', textDomain) : __('Inactive', textDomain)"
          @change="$emit('update:status', $event ? 'publish' : 'draft')"
        />

        <div class="relative" data-builder-menu>
          <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-xl text-slate-700 transition hover:border-primary-200 hover:bg-primary-50"
            :aria-label="__('Actions menu', textDomain)"
            @click="toggleMenu"
          >
            ⋮
          </button>
          <div
            v-if="menuOpen"
            class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-lg border border-slate-200 bg-white p-2 shadow-soft"
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
          class="inline-flex h-11 w-11 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-base font-semibold text-slate-700 transition hover:border-primary-200 hover:bg-primary-50"
          :aria-label="__('Open documentation', textDomain)"
        >
          ?
        </a>
      </div>
    </div>
  </header>
</template>
