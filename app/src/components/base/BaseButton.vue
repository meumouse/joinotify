<script setup>
const props = defineProps({
  title: { type: String, required: true },
  href: { type: String, default: '' },
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'ghost', 'danger'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md'].includes(value),
  },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
});

const emit = defineEmits(['click']);

const variantClasses = {
  primary: 'bg-primary-700 text-white shadow-sm hover:bg-primary-800 focus-visible:ring-primary-100',
  secondary: 'bg-white text-ink ring-1 ring-slate-200 hover:bg-slate-50 focus-visible:ring-primary-100',
  ghost: 'bg-transparent text-ink hover:bg-slate-100 focus-visible:ring-primary-100',
  danger: 'bg-danger text-white shadow-sm hover:opacity-90 focus-visible:ring-red-100',
};

const sizeClasses = {
  sm: 'px-3 py-2 text-sm',
  md: 'px-4 py-2.5 text-sm',
};

function handleClick(event) {
  if (props.disabled || props.loading) {
    event.preventDefault();
    event.stopPropagation();
    return;
  }

  emit('click', event);
}
</script>

<template>
  <component
    :is="href ? 'a' : 'button'"
    :href="href || undefined"
    :type="href ? undefined : type"
    :aria-disabled="disabled || loading ? 'true' : undefined"
    :tabindex="disabled || loading ? -1 : undefined"
    class="inline-flex items-center justify-center gap-2 rounded-[8px] font-medium outline-none transition focus-visible:ring-4"
    :class="[
      sizeClasses[size],
      variantClasses[variant],
      disabled || loading ? 'cursor-not-allowed opacity-60' : '',
    ]"
    @click="handleClick"
  >
    <span
      v-if="loading"
      class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    />
    <span>{{ title }}</span>
  </component>
</template>
