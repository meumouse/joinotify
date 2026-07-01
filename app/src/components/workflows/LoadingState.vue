<!--
  LoadingState.vue

  Static skeleton placeholder for the workflows screen, rendered while the
  list is loading. Shows shimmering blocks that mimic the toolbar, table
  header, and rows for both the desktop table and mobile card layouts.

  @since 2.0.0
-->
<template>
  <div class="space-y-4">
    <div class="h-12 w-56 rounded-[8px] bg-shell-100" />
    <div class="flex gap-2 overflow-hidden rounded-[8px] bg-white p-2 shadow-[0_1px_0_rgba(0,0,0,0.02)]">
      <div class="h-10 w-24 rounded-[8px] bg-shell-100" />
      <div class="h-10 w-24 rounded-[8px] bg-shell-100" />
      <div class="h-10 w-24 rounded-[8px] bg-shell-100" />
    </div>

    <div class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-[0_1px_0_rgba(0,0,0,0.02)]">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="h-10 w-72 rounded-[8px] bg-shell-100" />
        <div class="flex gap-2">
          <div class="h-10 w-24 rounded-[8px] bg-shell-100" />
          <div class="h-10 w-24 rounded-[8px] bg-shell-100" />
        </div>
      </div>

      <div class="mt-4 hidden md:block">
        <div class="grid grid-cols-4 gap-4 border-b border-slate-200 pb-3">
          <div class="h-4 w-16 rounded bg-shell-100" />
          <div class="h-4 w-16 rounded bg-shell-100" />
          <div class="h-4 w-16 rounded bg-shell-100" />
          <div class="h-4 w-16 rounded bg-shell-100" />
        </div>
        <div class="space-y-4 py-4">
          <div v-for="row in 4" :key="row" class="grid grid-cols-4 gap-4">
            <div class="h-10 rounded-[8px] bg-shell-100" />
            <div class="h-10 rounded-[8px] bg-shell-100" />
            <div class="h-10 rounded-[8px] bg-shell-100" />
            <div class="h-10 rounded-[8px] bg-shell-100" />
          </div>
        </div>
      </div>

      <div class="mt-4 space-y-3 md:hidden">
        <div v-for="row in 3" :key="row" class="rounded-[8px] border border-slate-200 p-4">
          <div class="h-5 w-2/3 rounded bg-shell-100" />
          <div class="mt-3 h-4 w-1/2 rounded bg-shell-100" />
          <div class="mt-4 h-4 w-full rounded bg-shell-100" />
        </div>
      </div>
    </div>
  </div>
</template>
