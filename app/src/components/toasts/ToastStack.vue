<script setup>

/**
 * ToastStack.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../utils/i18n';

defineProps({
  toasts: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['dismiss']);

function toastIconSvg(tone) {
  const icons = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2 1.75 20h20.5L12 2zm0 4.65 6.06 10.65H5.94L12 6.65z" fill="currentColor"/><path d="M11 9h2v5h-2zm0 6h2v2h-2z" fill="currentColor"/></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M11 11h2v6h-2zm0-4h2v2h-2z" fill="currentColor"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M11 10h2v7h-2zm0-4h2v2h-2z" fill="currentColor"/></svg>',
  };

  return icons[tone] || icons.info;
}

function toastShellClass(tone) {
  const classes = {
    success: 'toast-success',
    warning: 'toast-warning',
    error: 'toast-danger',
    info: 'toast-info',
  };

  return classes[tone] || classes.info;
}

function toastHeaderClass(tone) {
  const classes = {
    success: 'bg-success text-white',
    warning: 'bg-warning text-dark',
    error: 'bg-danger text-white',
    info: 'bg-info text-white',
  };

  return classes[tone] || classes.info;
}

function toastProgressClass(tone) {
  const classes = {
    success: 'bg-success [animation:joinotify-toast-progress_3s_linear_forwards]',
    warning: 'bg-warning [animation:joinotify-toast-progress_3s_linear_forwards]',
    error: 'bg-danger [animation:joinotify-toast-progress_3s_linear_forwards]',
    info: 'bg-info [animation:joinotify-toast-progress_3s_linear_forwards]',
  };

  return classes[tone] || classes.info;
}
</script>

<template>
  <div class="pointer-events-none fixed right-3 top-12 z-[1090] w-[350px] max-w-full" aria-live="polite" aria-atomic="true">
    <TransitionGroup name="joinotify-toast" tag="div" class="space-y-3">
      <article
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto relative overflow-hidden rounded-lg border border-transparent bg-white shadow-[0_0.275rem_1.25rem_rgba(11,15,25,0.05),0_0.25rem_0.5625rem_rgba(11,15,25,0.03)] transition-all duration-200 ease-out"
        :class="[toastShellClass(toast.tone), toast.closing ? 'translate-y-1 opacity-0' : 'translate-y-0 opacity-100']"
      >
        <header class="flex items-center border-0 px-4 py-2 font-bold" :class="toastHeaderClass(toast.tone)">
          <span class="me-2 inline-flex h-5 w-5 shrink-0 text-current" v-html="toastIconSvg(toast.tone)" />
          <span class="me-auto min-w-0 truncate">{{ toast.title }}</span>
          <button
            type="button"
            class="ms-2 box-content flex h-4 w-4 shrink-0 items-center justify-center rounded border-0 bg-transparent p-[0.25rem] opacity-50 transition hover:opacity-75 focus:outline-none focus:opacity-100 disabled:pointer-events-none disabled:select-none disabled:opacity-25"
            :aria-label="__('Close', textDomain)"
            @click="$emit('dismiss', toast.id)"
          >
            <span class="inline-flex h-3 w-3" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none">
                <path d="M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z" fill="currentColor"/>
              </svg>
            </span>
            <span class="sr-only">{{ __('Close', textDomain) }}</span>
          </button>
        </header>

        <div class="px-4 py-4 text-[15px] leading-6 text-slate-600">
          {{ toast.message }}
        </div>

        <div class="h-[3px] w-full origin-left" :class="toastProgressClass(toast.tone)" />
      </article>
    </TransitionGroup>
  </div>
</template>
