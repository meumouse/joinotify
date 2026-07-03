<!--
  BuilderPanel.vue

  Generic bordered card/section wrapper used across the builder screens.
  Purely presentational: renders slotted content inside a consistent panel
  shell so sidebar and canvas sections share the same visual style.

  @since 2.0.0
-->
<template>
  <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
    <slot />
  </section>
</template>
