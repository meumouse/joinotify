<!--
  BaseBadge.vue

  Small pill-style label used to highlight statuses, counts, or tags. Purely
  presentational: it renders slotted content inside a rounded badge and forwards
  any incoming class so callers can control the color scheme.

  @since 2.0.0
-->
<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset" :class="$attrs.class">
    <slot />
  </span>
</template>
