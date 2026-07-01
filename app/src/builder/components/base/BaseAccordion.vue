<script setup lang="ts">
/**
 * BaseAccordion.vue
 *
 * Lightweight collapsible section built on the native <details>/<summary>
 * elements. Renders a titled panel whose default slot content can be shown or
 * hidden, used to group builder settings into expandable blocks.
 *
 * @since 2.0.0
 */
defineProps({
  title: { type: String, default: '' },
  open: { type: Boolean, default: true },
});
</script>

<template>
  <details class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm" :open="open">
    <summary class="cursor-pointer list-none text-sm font-semibold text-slate-900">
      {{ title }}
    </summary>
    <div class="pt-4">
      <slot />
    </div>
  </details>
</template>
