<script setup>

/**
 * BaseButton.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';

const props = defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: '' },
  iconName: { type: String, default: '' },
  iconClass: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  color: {
    type: String,
    default: 'primary',
  },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

defineEmits(['click']);

const sizeClass = computed(() => {
  const classes = {
    sm: 'px-3 py-2 text-[13px]',
    md: 'px-5 py-3 text-[14px]',
    lg: 'px-6 py-3.5 text-[15px]',
  };

  return classes[props.size] || classes.md;
});

const colorClass = computed(() => {
  const classes = {
    primary: 'bg-primary-700 text-white hover:bg-primary-800',
    success: 'bg-success text-white hover:opacity-90',
    danger: 'bg-danger text-white hover:opacity-90',
    warning: 'bg-warning text-dark hover:opacity-90',
    info: 'bg-info text-white hover:opacity-90',
    white: 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50',
  };

  return classes[props.color] || classes.primary;
});

const spinnerClass = computed(() => {
  const sizes = {
    sm: 'h-3.5 w-3.5',
    md: 'h-4 w-4',
    lg: 'h-5 w-5',
  };

  return sizes[props.size] || sizes.md;
});
</script>

<template>
  <button
    type="button"
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-[8px] font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
    :class="[sizeClass, colorClass]"
    @click="$emit('click')"
  >
    <span
      v-if="loading"
      class="inline-flex shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent"
      :class="spinnerClass"
      aria-hidden="true"
    />
    <span v-else-if="icon" class="inline-flex shrink-0 leading-none" v-html="icon" />
    <span>{{ title }}</span>
  </button>
</template>
