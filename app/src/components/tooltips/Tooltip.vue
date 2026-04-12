<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
const tooltipEl = ref(null);
const tooltipStyle = ref({});

let closeTimer = null;
let resizeHandler = null;
let scrollHandler = null;
let rafId = 0;

const isVisible = computed(() => open.value && !props.disabled && Boolean(props.content));

const tooltipClass = computed(() => {
  const map = {
    top: 'bs-tooltip-top',
    bottom: 'bs-tooltip-bottom',
    left: 'bs-tooltip-start',
    right: 'bs-tooltip-end',
  };

  return map[props.placement] || map.top;
});

const arrowClass = computed(() => {
  const map = {
    top: 'bottom-[-0.25rem] left-1/2 -translate-x-1/2',
    bottom: 'top-[-0.25rem] left-1/2 -translate-x-1/2',
    left: 'right-[-0.25rem] top-1/2 -translate-y-1/2',
    right: 'left-[-0.25rem] top-1/2 -translate-y-1/2',
  };

  return map[props.placement] || map.top;
});

function clearHideTimer() {
  if (closeTimer) {
    clearTimeout(closeTimer);
    closeTimer = null;
  }
}

function openTooltip() {
  if (props.disabled || !props.content) return;
  clearHideTimer();
  open.value = true;
}

function closeTooltip() {
  clearHideTimer();
  closeTimer = window.setTimeout(() => {
    open.value = false;
  }, 50);
}

function handleFocusOut(event) {
  if (!rootEl.value?.contains(event.relatedTarget)) {
    closeTooltip();
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    open.value = false;
  }
}

function updatePosition() {
  if (!rootEl.value || !tooltipEl.value) return;

  const triggerRect = rootEl.value.getBoundingClientRect();
  const tipRect = tooltipEl.value.getBoundingClientRect();
  const gap = 8;
  const viewportMargin = 8;

  let top = 0;
  let left = 0;
  let transform = '';

  if (props.placement === 'top') {
    top = triggerRect.top - tipRect.height - gap;
    left = triggerRect.left + triggerRect.width / 2;
    transform = 'translate3d(-50%, 0, 0)';
  } else if (props.placement === 'bottom') {
    top = triggerRect.bottom + gap;
    left = triggerRect.left + triggerRect.width / 2;
    transform = 'translate3d(-50%, 0, 0)';
  } else if (props.placement === 'left') {
    top = triggerRect.top + triggerRect.height / 2;
    left = triggerRect.left - tipRect.width - gap;
    transform = 'translate3d(0, -50%, 0)';
  } else {
    top = triggerRect.top + triggerRect.height / 2;
    left = triggerRect.right + gap;
    transform = 'translate3d(0, -50%, 0)';
  }

  if (props.placement === 'top' || props.placement === 'bottom') {
    const minLeft = tipRect.width / 2 + viewportMargin;
    const maxLeft = window.innerWidth - tipRect.width / 2 - viewportMargin;
    left = Math.min(Math.max(left, minLeft), maxLeft);
  } else {
    const minTop = tipRect.height / 2 + viewportMargin;
    const maxTop = window.innerHeight - tipRect.height / 2 - viewportMargin;
    top = Math.min(Math.max(top, minTop), maxTop);
  }

  tooltipStyle.value = {
    top: `${top}px`,
    left: `${left}px`,
    transform,
  };
}

function schedulePositionUpdate() {
  if (rafId) {
    cancelAnimationFrame(rafId);
  }

  rafId = window.requestAnimationFrame(() => {
    rafId = 0;
    updatePosition();
  });
}

function attachListeners() {
  if (resizeHandler) return;

  resizeHandler = () => schedulePositionUpdate();
  scrollHandler = () => schedulePositionUpdate();

  window.addEventListener('resize', resizeHandler);
  window.addEventListener('scroll', scrollHandler, true);
}

function detachListeners() {
  if (resizeHandler) {
    window.removeEventListener('resize', resizeHandler);
    resizeHandler = null;
  }

  if (scrollHandler) {
    window.removeEventListener('scroll', scrollHandler, true);
    scrollHandler = null;
  }
}

watch(isVisible, async (value) => {
  if (value) {
    await nextTick();
    schedulePositionUpdate();
    attachListeners();
    return;
  }

  detachListeners();
});

watch(
  () => [props.placement, props.content],
  () => {
    if (isVisible.value) {
      schedulePositionUpdate();
    }
  },
);

onBeforeUnmount(() => {
  clearHideTimer();
  detachListeners();

  if (rafId) {
    cancelAnimationFrame(rafId);
  }
});
</script>

<template>
  <span
    ref="rootEl"
    class="relative inline-flex"
    @mouseenter="openTooltip"
    @mouseleave="closeTooltip"
    @focusin="openTooltip"
    @focusout="handleFocusOut"
    @keydown="handleKeydown"
  >
    <slot />
  </span>

  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isVisible"
        ref="tooltipEl"
        :class="tooltipClass"
        class="pointer-events-none fixed z-[10050] w-max max-w-[200px] rounded-[0.375rem] bg-black/90 px-3 py-1.5 text-center text-[0.875rem] leading-[1.5] text-white shadow-[0_0.5rem_1rem_rgba(0,0,0,0.15)]"
        :style="tooltipStyle"
        role="tooltip"
      >
        <span
          class="absolute h-2.5 w-2.5 rotate-45 bg-black/90 shadow-[0_0_0_1px_rgba(255,255,255,0.08)]"
          :class="arrowClass"
          aria-hidden="true"
        />
        <span class="relative z-[1] block">{{ content }}</span>
      </div>
    </Transition>
  </Teleport>
</template>
