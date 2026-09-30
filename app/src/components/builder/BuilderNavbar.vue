<script setup>
/**
 * BuilderNavbar.vue
 *
 * Sticky top navigation bar for the workflow builder. It displays the workflow
 * title and status, exposes save/test/status controls, and provides an actions
 * menu (edit title, export, new, back) via emitted events.
 *
 * @since 2.0.0
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BaseBadge from '../base/BaseBadge.vue';
import BaseButton from '../base/BaseButton.vue';
import BaseSwitch from '../base/BaseSwitch.vue';
import BrandMark from '../brand/BrandMark.vue';
import { DotsHorizontalRounded } from '@boxicons/vue';
import { ArrowLeftStroke, ArrowToBottomStroke, Cog, FilePlus, Grid, Pencil } from '@boxicons/vue';

const props = defineProps({
  title: { type: String, default: '' },
  status: { type: String, default: 'draft' },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false },
  statusLoading: { type: Boolean, default: false },
  docsUrl: { type: String, default: '#' },
  newUrl: { type: String, default: '#' },
  backUrl: { type: String, default: '#' },
  dashboardUrl: { type: String, default: '#' },
  settingsUrl: { type: String, default: '#' },
});

const emit = defineEmits(['update:status', 'test', 'new', 'back', 'export', 'edit-title', 'save']);

const menuOpen = ref(false);

/**
 * Toggle the actions menu open or closed.
 *
 * @since 2.0.0
 * @returns {void}
 */
function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}

/**
 * Close the actions menu.
 *
 * @since 2.0.0
 * @returns {void}
 */
function closeMenu() {
  menuOpen.value = false;
}

/**
 * Handle a click on a navigational menu link.
 *
 * The item is rendered as a real anchor so it can be opened in a new tab via
 * right-click or a modified click. Modified clicks (Ctrl/Cmd/Shift/Alt or a
 * non-primary button) are left to the browser so the native "open in new
 * tab/window" behaviour works; a plain left-click is intercepted and routed
 * through the SPA handler instead of triggering a full page reload.
 *
 * @since 2.0.0
 * @param {MouseEvent} event The click event.
 * @param {string} action The emit name to trigger on a plain left-click.
 * @returns {void}
 */
function handleNavClick(event, action) {
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || (event.button && event.button !== 0)) {
    closeMenu();
    return;
  }

  event.preventDefault();
  emit(action);
  closeMenu();
}

/**
 * Close the actions menu when a click occurs outside of it.
 *
 * @since 2.0.0
 * @param {MouseEvent} event The window click event.
 * @returns {void}
 */
function handleWindowClick(event) {
  if (!event.target.closest?.('[data-builder-menu]')) {
    closeMenu();
  }
}

/**
 * Compute the CSS classes for the status badge based on the workflow status.
 *
 * @since 2.0.0
 * @returns {string} The Tailwind classes for the badge.
 */
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
  <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-md backdrop-saturate-150">
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
        <BaseButton :title="__('Save flow', textDomain)" variant="primary" :loading="saving" :disabled="!dirty && !saving" @click="$emit('save')" />
        <BaseButton :title="__('Run test', textDomain)" variant="secondary" :loading="loading" @click="$emit('test')" />
        <BaseSwitch
          :model-value="status === 'publish'"
          :label="statusLoading ? __('Saving...', textDomain) : (status === 'publish' ? __('Active', textDomain) : __('Inactive', textDomain))"
          :loading="statusLoading"
          active-class="bg-[#22c55e]"
          @change="$emit('update:status', $event ? 'publish' : 'draft')"
        />

        <div class="relative" data-builder-menu>
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
            :aria-label="__('Actions menu', textDomain)"
            @click="toggleMenu"
          >
            <DotsHorizontalRounded :width="22" :height="22" />
          </button>
          <div
            v-if="menuOpen"
            class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-soft"
          >
            <button type="button" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('edit-title'); closeMenu()">
              <Pencil class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
              {{ __('Edit workflow title', textDomain) }}
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 hover:bg-primary-50" @click="$emit('export'); closeMenu()">
              <ArrowToBottomStroke class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
              {{ __('Export workflow', textDomain) }}
            </button>
            <a :href="newUrl" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 no-underline hover:bg-primary-50" @click="handleNavClick($event, 'new')">
              <FilePlus class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
              {{ __('Create a new workflow', textDomain) }}
            </a>
            <a :href="dashboardUrl" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 no-underline hover:bg-primary-50" @click="handleNavClick($event, 'back')">
              <Grid class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
              {{ __('View all workflows', textDomain) }}
            </a>
            <a :href="settingsUrl" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 no-underline hover:bg-primary-50" @click="closeMenu">
              <Cog class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
              {{ __('Plugin settings', textDomain) }}
            </a>
            <a :href="backUrl" class="flex w-full items-center gap-3 rounded-[8px] px-4 py-3 text-left text-sm text-slate-700 no-underline hover:bg-primary-50" @click="handleNavClick($event, 'back')">
              <ArrowLeftStroke class="h-5 w-5 shrink-0 text-slate-400" aria-hidden="true" />
              {{ __('Back to dashboard', textDomain) }}
            </a>
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
