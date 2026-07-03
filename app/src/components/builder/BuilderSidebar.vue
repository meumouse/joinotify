<!--
  BuilderSidebar.vue

  Bordered panel shell used for the builder's sidebar sections (action
  library, node settings, etc). Purely presentational: renders slotted
  content inside a consistent card style.

  @since 2.0.0
-->
<template>
  <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-soft">
    <slot />
  </section>
</template>
