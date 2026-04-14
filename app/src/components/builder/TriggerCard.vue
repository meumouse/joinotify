<script setup>
const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconSvg: { type: String, default: '' },
  selected: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
  <button
    type="button"
    class="flex min-h-[154px] flex-col rounded-[14px] border bg-white p-5 text-left transition"
    :class="selected
      ? 'border-blue-600 bg-blue-600 text-white shadow-[0_10px_30px_rgba(37,99,235,0.18)] ring-1 ring-blue-500/30'
      : 'border-slate-200 hover:border-blue-200 hover:shadow-[0_10px_24px_rgba(15,23,42,0.06)]'"
    @click="$emit('click')"
  >
    <div
      class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded-full text-sm font-semibold"
      :class="selected ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'"
      aria-hidden="true"
    >
      <span
        v-if="iconSvg"
        class="trigger-icon flex h-full w-full items-center justify-center p-2"
        v-html="iconSvg"
      />
      <span v-else>
        {{ icon }}
      </span>
    </div>
    <h3 class="mt-4 text-center text-[17px] font-semibold leading-6" :class="selected ? 'text-white' : 'text-slate-900'">
      {{ title }}
    </h3>
    <p class="mt-4 text-center text-sm leading-6" :class="selected ? 'text-white/80' : 'text-slate-500'">
      {{ description }}
    </p>
  </button>
</template>

<style scoped>
.trigger-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
  color: currentColor;
  fill: currentColor;
}
</style>
