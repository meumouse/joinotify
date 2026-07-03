<script setup>
/**
 * Generic labeled field wrapper used across the login forms.
 *
 * @since 1.0.0
 * @type {Object}
 */
defineProps({
  forId: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  helper: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <div class="space-y-2">
    <label :for="forId" class="mb-1 block text-sm font-semibold leading-none text-slate-700">
      {{ label }}
    </label>
    <slot />
    <p v-if="helper" class="text-xs leading-5 text-slate-500">
      {{ helper }}
    </p>
  </div>
</template>
