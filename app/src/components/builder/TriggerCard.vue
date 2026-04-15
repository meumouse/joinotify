<script setup>
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
    class="flex min-h-[154px] flex-col rounded-xl border bg-white p-5 text-left transition"
    :class="selected
      ? 'border-blue-600 bg-blue-600 text-white ring-2 ring-blue-500/30'
      : 'border-slate-200 hover:border-blue-200'"
    @click="$emit('click')"
  >
    <div class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border" :class="selected ? 'border-white/20 bg-white/10' : 'border-slate-200 bg-slate-50'">
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
      <span v-else class="text-sm font-semibold uppercase tracking-[0.18em]" :class="selected ? 'text-white' : 'text-slate-500'">
        {{ icon }}
      </span>
    </div>
    <p v-if="contextLabel" class="mt-3 text-center text-[11px] font-semibold uppercase tracking-[0.18em]" :class="selected ? 'text-white/70' : 'text-slate-400'">
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
