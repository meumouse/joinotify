<script setup>
/**
 * BrandMark.vue
 *
 * Renders the Joinotify logo image, resolving the SVG source from a color
 * variant (white, dark, primary) and computing its pixel height from a size
 * keyword or explicit number. Used for consistent brand display across the UI.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  alt: { type: String, default: () => __('Joinotify', textDomain) },
  size: { type: [String, Number], default: 'md' },
  title: { type: String, default: () => __('Joinotify', textDomain) },
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

/**
 * Resolve the logo image source URL for the current variant.
 *
 * @since 2.0.0
 * @returns {string} Full path to the variant's SVG file.
 */
const src = computed(() => `${props.basePath}/${variants[props.variant]}`);

/**
 * Resolve the logo height in pixels from a numeric size or size keyword.
 *
 * @since 2.0.0
 * @returns {number} Height in pixels, defaulting to the medium size.
 */
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
