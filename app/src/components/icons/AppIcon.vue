<script setup>

/**
 * AppIcon.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { ICONS } from '../../config/icons';

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  decorative: {
    type: Boolean,
    default: true,
  },
  title: {
    type: String,
    default: '',
  },
});

const icon = computed(() => ICONS[props.name] || ICONS.fallback);
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    :viewBox="icon.viewBox"
    :role="decorative ? undefined : 'img'"
    :aria-hidden="decorative ? 'true' : 'false'"
    fill="none"
  >
    <title v-if="!decorative && title">{{ title }}</title>

    <component
      v-for="(element, index) in icon.elements"
      :is="element.tag"
      :key="index"
      v-bind="element.attrs"
    />
  </svg>
</template>
