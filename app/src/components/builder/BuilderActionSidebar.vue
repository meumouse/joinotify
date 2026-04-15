<script setup lang="ts">
defineProps({
  actions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  open: { type: Boolean, default: false },
});

defineEmits(['close', 'select']);
</script>

<template>
  <div class="pointer-events-none absolute inset-y-0 right-0 z-20 flex h-full">
    <div
      class="pointer-events-auto h-full transition-[transform,opacity] duration-300 ease-out"
      :class="open ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'"
    >
      <aside class="ml-4 flex h-full w-[24rem] flex-col overflow-hidden rounded-l-[28px] border-l border-slate-200 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.12)]">
        <div class="flex items-start justify-between border-b border-slate-200 px-5 py-5">
          <div>
            <h2 class="text-[1.35rem] font-semibold tracking-tight text-slate-900">
              Add an action
            </h2>
            <p class="mt-2 max-w-[18rem] text-sm leading-6 text-slate-500">
              Select one or more actions for the automation workflow.
            </p>
          </div>

          <button
            type="button"
            class="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full border border-transparent text-2xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close panel"
            @click="$emit('close')"
          >
            x
          </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
          <template v-if="loading">
            <div
              v-for="index in 5"
              :key="`action-skeleton-${index}`"
              class="joinotify-skeleton mb-3 flex items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4"
            >
              <div class="joinotify-skeleton mt-0.5 h-10 w-10 rounded-full bg-slate-200/70" />
              <div class="min-w-0 flex-1">
                <div class="joinotify-skeleton h-4 w-32 rounded-full bg-slate-200/75" />
                <div class="joinotify-skeleton mt-3 h-3 w-40 rounded-full bg-slate-200/60" />
                <div class="joinotify-skeleton mt-2 h-3 w-28 rounded-full bg-slate-200/60" />
              </div>
            </div>
          </template>

          <template v-else>
            <button
              v-for="action in actions"
              :key="action.action || action.id"
              type="button"
              class="mb-3 flex w-full items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4 text-left transition hover:border-slate-300 hover:shadow-[0_12px_30px_rgba(15,23,42,0.08)]"
              @click="$emit('select', action.action || action.id)"
            >
              <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-100 bg-slate-50 text-slate-500">
                <span
                  v-if="action.iconSvg && String(action.iconSvg).trim().startsWith('<svg')"
                  class="flex h-5 w-5 items-center justify-center text-primary-700"
                  v-html="action.iconSvg"
                />
                <span v-else-if="action.icon" class="text-[0.72rem] font-semibold uppercase tracking-[0.18em]">
                  {{ String(action.icon).slice(0, 1) }}
                </span>
                <span v-else class="text-[0.72rem] font-semibold uppercase tracking-[0.18em]">
                  {{ String(action.title || action.label || 'A').slice(0, 1) }}
                </span>
              </div>

              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-base font-semibold leading-6 text-slate-900">
                    {{ action.title || action.label }}
                  </h3>
                </div>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                  {{ action.description }}
                </p>
              </div>
            </button>

            <div v-if="!actions.length" class="rounded-[14px] border border-dashed border-slate-300 px-4 py-8 text-center">
              <p class="text-sm font-medium text-slate-700">No actions available.</p>
              <p class="mt-1 text-sm leading-6 text-slate-500">Check the backend configuration or the selected trigger context.</p>
            </div>
          </template>
        </div>
      </aside>
    </div>
  </div>
</template>
