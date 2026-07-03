<!--
  BaseDropdown.vue

  Lightweight dropdown container that positions a floating panel beneath a
  trigger element. It exposes a named "trigger" slot for the control and a
  default slot for the menu contents, leaving open/close behavior to the caller.

  @since 2.0.0
-->
<template>
  <div class="relative">
    <slot name="trigger" />
    <div class="absolute right-0 top-full z-20 mt-2 min-w-56 rounded-lg border border-slate-200 bg-white p-2 shadow-xl">
      <slot />
    </div>
  </div>
</template>
