<script setup>
/**
 * TriggerCard.vue
 *
 * Selectable card representing a single trigger in the trigger setup screen.
 * Renders the trigger's context/icon avatar, context label, title and
 * description, shows a checkmark when selected, and emits a click event when
 * pressed.
 *
 * @since 2.0.0
 */
const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  contextLabel: { type: String, default: '' },
  contextIconSvg: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconSvg: { type: String, default: '' },
  selected: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="relative flex min-h-[154px] flex-col rounded-xl border-2 bg-white p-5 text-left transition"
    :class="selected
      ? 'border-primary-700'
      : 'border-slate-200 hover:border-primary-200'"
    @click="$emit('click')"
  >
    <span
      v-if="selected"
      class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary-700 text-white"
      aria-hidden="true"
    >
      <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3.5 8.5l3 3 6-7" />
      </svg>
    </span>
    <div class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50">
      <span
        v-if="contextIconSvg"
        class="trigger-context-icon flex h-full w-full items-center justify-center p-1.5"
        v-html="contextIconSvg"
      />
      <span
        v-else-if="iconSvg"
        class="trigger-icon flex h-full w-full items-center justify-center p-2"
        v-html="iconSvg"
      />
      <span v-else class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
        {{ icon }}
      </span>
    </div>
    <p v-if="contextLabel" class="mt-3 text-center text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
      {{ contextLabel }}
    </p>
    <h3 class="mt-4 text-center text-[17px] font-semibold leading-6 text-slate-900">
      {{ title }}
    </h3>
    <p class="mt-4 text-center text-sm leading-6 text-slate-500">
      {{ description }}
    </p>
  </button>
</template>

<style scoped>
.trigger-context-icon :deep(svg),
.trigger-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
  color: currentColor;
  fill: currentColor;
}
</style>
