<script setup>
import { computed } from 'vue';

const props = defineProps({
  alt: { type: String, default: 'Joinotify' },
  size: { type: [String, Number], default: 'md' },
  title: { type: String, default: '' },
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['white', 'dark', 'primary'].includes(value),
  },
  basePath: { type: String, default: '/wp-content/plugins/joinotify/assets/brand' },
});

const variants = {
  white: 'logo-joinotify-white.svg',
  dark: 'logo-joinotify-dark.svg',
  primary: 'logo-joinotify-primary.svg',
};

const sizeMap = {
  xs: 24,
  sm: 32,
  md: 40,
  lg: 48,
  xl: 56,
};

const src = computed(() => `${props.basePath}/${variants[props.variant]}`);
const height = computed(() => {
  if (typeof props.size === 'number') {
    return props.size;
  }

  return sizeMap[props.size] || sizeMap.md;
});
</script>

<template>
  <img
    :alt="alt"
    :height="height"
    :src="src"
    :style="{ height: `${height}px`, width: 'auto' }"
    class="block shrink-0"
    :title="title || alt"
    decoding="async"
    loading="eager"
  >
</template>
