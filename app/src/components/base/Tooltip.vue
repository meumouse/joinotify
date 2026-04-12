<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  content: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  placement: {
    type: String,
    default: 'top',
    validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value),
  },
});

const open = ref(false);
const rootEl = ref(null);

const placementClasses = computed(() => {
  const map = {
    top: 'bottom-full left-1/2 mb-2 -translate-x-1/2',
    bottom: 'left-1/2 top-full mt-2 -translate-x-1/2',
    left: 'right-full top-1/2 mr-2 -translate-y-1/2',
    right: 'left-full top-1/2 ml-2 -translate-y-1/2',
  };

  return map[props.placement] || map.top;
});

const arrowClasses = computed(() => {
  const map = {
    top: 'left-1/2 top-full -mt-1.5 -translate-x-1/2',
    bottom: 'left-1/2 bottom-full -mb-1.5 -translate-x-1/2',
    left: 'left-full top-1/2 -mr-1.5 -translate-y-1/2',
    right: 'right-full top-1/2 -ml-1.5 -translate-y-1/2',
  };

  return map[props.placement] || map.top;
});

function openTooltip() {
  if (props.disabled || !props.content) return;
  open.value = true;
}

function closeTooltip() {
  open.value = false;
}

function handleFocusOut(event) {
  if (!rootEl.value?.contains(event.relatedTarget)) {
    closeTooltip();
  }
}
</script>

<template>
  <span
    ref="rootEl"
    class="relative inline-flex"
    @mouseenter="openTooltip"
    @mouseleave="closeTooltip"
    @focusin="openTooltip"
    @focusout="handleFocusOut"
  >
    <slot />

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="translate-y-1 scale-95 opacity-0"
      enter-to-class="translate-y-0 scale-100 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="translate-y-0 scale-100 opacity-100"
      leave-to-class="translate-y-1 scale-95 opacity-0"
    >
      <span
        v-if="open && !disabled && content"
        class="pointer-events-none absolute z-[70] whitespace-nowrap rounded-[10px] border border-slate-200 bg-slate-900 px-3 py-2 text-[12px] font-medium leading-none text-white shadow-[0_12px_30px_rgba(15,23,42,0.2)]"
        :class="placementClasses"
        role="tooltip"
      >
        {{ content }}
        <span class="absolute h-2.5 w-2.5 rotate-45 bg-slate-900" :class="arrowClasses" />
      </span>
    </Transition>
  </span>
</template>
