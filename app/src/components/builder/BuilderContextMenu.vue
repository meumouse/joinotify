<!--
  BuilderContextMenu.vue

  Floating menu shell used for canvas right-click context menus. Purely
  presentational: it renders slotted menu items inside a styled container and
  leaves positioning and open/close behavior to the caller.

  @since 2.0.0
-->
<template>
  <div class="rounded-lg border border-slate-200 bg-white p-2 shadow-xl">
    <slot />
  </div>
</template>
