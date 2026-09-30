<script setup>
/**
 * BaseDrawer.vue
 *
 * Slide-in panel anchored to the right edge of the viewport, teleported to the
 * document body and shown as an overlay. Displays a title and slotted content,
 * and emits a "close" event when the backdrop or close controls are clicked.
 * It opens below the WordPress admin bar when the bar is shown.
 *
 * @since 2.0.0
 * @version 2.5.0
 */
import { Teleport } from 'vue';
import { __, textDomain } from '../../utils/i18n';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
});

defineEmits(['close']);
</script>

<template>
  <Teleport to="body">
    <!-- Starts below the WordPress admin bar, which sits above any z-index the panel could take. -->
    <div v-if="open" class="fixed inset-x-0 bottom-0 top-[var(--wp-admin--admin-bar--height,0px)] z-[9999] flex justify-end bg-slate-950/35">
      <button type="button" class="absolute inset-0" :aria-label="__('Close drawer', textDomain)" @click="$emit('close')" />
      <aside class="relative z-10 h-full w-full max-w-[460px] overflow-y-auto border-l border-slate-200 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
          <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
          <button type="button" class="rounded-[8px] px-3 py-1.5 text-sm text-slate-500 hover:bg-primary-50 hover:text-primary-700" @click="$emit('close')">
            {{ __('Close', textDomain) }}
          </button>
        </div>
        <div class="p-5">
          <slot />
        </div>
      </aside>
    </div>
  </Teleport>
</template>
