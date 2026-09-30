<script setup>

/**
 * ReviewPromptModal.vue frontend component.
 *
 * Asks the site owner for a WordPress.org review once the plugin has been in
 * use for a while. The review button is a real link, so the new tab opens from
 * the click itself and is never caught by a popup blocker.
 *
 * @since 2.5.0
 * @version 2.5.0
 */
import { __, textDomain } from '../../../../utils/i18n';
import ModalDialog from '../../../../components/modals/ModalDialog.vue';

defineProps({
  open: { type: Boolean, default: false },
  reviewUrl: { type: String, default: '' },
});

defineEmits(['rate', 'later', 'dismiss']);
</script>

<template>
  <ModalDialog
    :open="open"
    :eyebrow="__('Joinotify', textDomain)"
    :title="__('Enjoying Joinotify?', textDomain)"
    size-class="max-w-lg"
    @close="$emit('later')"
  >
    <div class="flex items-center gap-1 text-amber-400" aria-hidden="true">
      <svg v-for="star in 5" :key="star" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
        <path d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11a.56.56 0 0 0 .47.35l5.52.44a.56.56 0 0 1 .32.99l-4.2 3.6a.56.56 0 0 0-.18.56l1.28 5.39a.56.56 0 0 1-.84.61l-4.73-2.89a.56.56 0 0 0-.59 0l-4.73 2.89a.56.56 0 0 1-.84-.61l1.28-5.39a.56.56 0 0 0-.18-.56l-4.2-3.6a.56.56 0 0 1 .32-.99l5.52-.44a.56.56 0 0 0 .47-.35l2.13-5.11Z" />
      </svg>
    </div>

    <p class="mt-4 text-[14px] leading-6 text-slate-600">
      {{ __('You have been automating your messages with Joinotify for a while now. If it is helping you, would you take a minute to rate it on WordPress.org?', textDomain) }}
    </p>
    <p class="mt-3 text-[14px] leading-6 text-slate-600">
      {{ __('Your review helps other people find the plugin and keeps us improving it.', textDomain) }}
    </p>

    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
      <button
        type="button"
        class="text-[13px] font-medium text-slate-500 underline-offset-4 transition hover:text-slate-700 hover:underline"
        @click="$emit('dismiss')"
      >
        {{ __('I already did', textDomain) }}
      </button>

      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
        <button
          type="button"
          class="rounded-[8px] border border-slate-200 px-6 py-3 text-[14px] font-medium text-slate-600 transition hover:bg-slate-50"
          @click="$emit('later')"
        >
          {{ __('Maybe later', textDomain) }}
        </button>
        <a
          :href="reviewUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center justify-center rounded-[8px] bg-primary-600 px-6 py-3 text-[14px] font-semibold text-white no-underline transition hover:bg-primary-700 hover:text-white focus:text-white"
          @click="$emit('rate')"
        >
          {{ __('Leave a review', textDomain) }}
        </a>
      </div>
    </div>
  </ModalDialog>
</template>
