<script setup>
/**
 * PageShellSkeleton.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */

const props = defineProps({
  variant: {
    type: String,
    default: 'settings',
  },
});

const loadingLabel = {
  settings: 'Loading the settings interface...',
  license: 'Loading the license interface...',
};
</script>

<template>
  <div class="joinotify-settings min-h-screen bg-[#f3f3f5]">
    <div class="w-full">
      <div class="flex items-start gap-3 px-10 pt-8">
        <div class="mt-1 flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-sm font-semibold text-slate-400 shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-200">
          <span>J</span>
        </div>

        <div class="pt-1">
          <div class="h-7 w-[18rem] max-w-[70vw] rounded-full bg-slate-200/80" />
          <div class="mt-4 h-4 w-[34rem] max-w-[70vw] rounded-full bg-slate-200/60" />
        </div>
      </div>

      <section class="mt-8 rounded-[8px] bg-white shadow-[0_1px_0_rgba(0,0,0,0.02)] ring-1 ring-slate-100">
        <div class="px-10 py-12">
          <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-6 py-10 text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ variant === 'license' ? 'License' : 'Settings' }}
            </p>
            <p class="mt-3 text-base font-medium text-slate-700">
              {{ loadingLabel[variant] || loadingLabel.settings }}
            </p>
            <p class="mt-2 text-sm text-slate-500">
              Please wait a moment while we load the data.
            </p>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
