<template>
  <div class="relative">
    <slot name="trigger" />
    <div class="absolute right-0 top-full z-20 mt-2 min-w-56 rounded-lg border border-slate-200 bg-white p-2 shadow-xl">
      <slot />
    </div>
  </div>
</template>
