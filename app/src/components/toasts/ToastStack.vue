<script setup>

/**
 * ToastStack.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../utils/i18n';
import AppIcon from '../icons/AppIcon.vue';

defineProps({
  toasts: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['dismiss']);

function toastIconName(tone) {
  const icons = {
    success: 'toast-success',
    warning: 'toast-warning',
    error: 'toast-error',
    info: 'toast-info',
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
          <AppIcon :name="toastIconName(toast.tone)" class="me-2 h-5 w-5 shrink-0 text-current" />
          <span class="me-auto min-w-0 truncate">{{ toast.title }}</span>
          <button
            type="button"
            class="ms-2 box-content flex h-4 w-4 shrink-0 items-center justify-center rounded border-0 bg-transparent p-[0.25rem] opacity-50 transition hover:opacity-75 focus:outline-none focus:opacity-100 disabled:pointer-events-none disabled:select-none disabled:opacity-25"
            :aria-label="__('Fechar', textDomain)"
            @click="$emit('dismiss', toast.id)"
          >
            <AppIcon name="close" class="h-3 w-3" />
            <span class="sr-only">{{ __('Fechar', textDomain) }}</span>
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
