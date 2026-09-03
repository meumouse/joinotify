<script setup>

/**
 * StepIndicator.vue frontend component.
 *
 * Numbered rail listing the wizard steps. Steps already left behind are
 * clickable so the user can go back and change an answer; steps ahead are not,
 * because each one is saved as it is passed.
 *
 * @since 2.3.0
 */
import { __, textDomain } from '../../../utils/i18n';

defineProps({
  steps: { type: Array, default: () => [] },
  currentIndex: { type: Number, default: 0 },
});

defineEmits(['navigate']);
</script>

<template>
  <ol class="flex flex-col gap-1" role="list">
    <li v-for="(step, index) in steps" :key="step.id">
      <component
        :is="index < currentIndex ? 'button' : 'div'"
        :type="index < currentIndex ? 'button' : undefined"
        class="flex w-full items-start gap-3 rounded-lg px-3 py-3 text-left transition"
        :class="[
          index === currentIndex ? 'bg-primary-50 ring-1 ring-primary-200' : '',
          index < currentIndex ? 'cursor-pointer hover:bg-slate-50' : '',
        ]"
        @click="index < currentIndex ? $emit('navigate', index) : undefined"
      >
        <span
          aria-hidden="true"
          class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
          :class="index < currentIndex
            ? 'bg-primary-700 text-white'
            : index === currentIndex
              ? 'bg-white text-primary-700 ring-2 ring-primary-700'
              : 'bg-slate-100 text-slate-400'"
        >
          {{ index < currentIndex ? '✓' : index + 1 }}
        </span>

        <span class="min-w-0">
          <span
            class="block text-[13px] font-semibold"
            :class="index <= currentIndex ? 'text-ink' : 'text-slate-400'"
          >{{ step.title }}</span>

          <span
            v-if="step.optional"
            class="mt-0.5 block text-[11px] font-medium uppercase tracking-[0.14em] text-shell-500"
          >{{ __('Optional', textDomain) }}</span>
        </span>
      </component>
    </li>
  </ol>
</template>
