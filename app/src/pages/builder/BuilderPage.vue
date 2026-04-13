<script setup>

/**
 * BuilderPage.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
defineProps({
  bootstrap: { type: Object, default: () => ({}) },
});
</script>

<template>
  <div class="min-h-screen bg-[#f3f3f5] px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto flex w-full max-w-[1240px] flex-col gap-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_12px_30px_rgba(16,32,51,0.04)]">
        <h1 class="text-2xl font-semibold text-ink">Builder</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
          Reserved page for the project builder. Use this area to place the visual builder flow without mixing it with settings or license.
        </p>
      </section>
    </div>
  </div>
</template>
