<script setup lang="ts">
/**
 * FieldGroup.vue
 *
 * Card-style container that groups related builder settings under an optional
 * title and description, rendering its fields via the default slot with
 * consistent spacing.
 *
 * @since 2.0.0
 */
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
});
</script>

<template>
  <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <header v-if="title || description" class="space-y-1">
      <h3 v-if="title" class="text-sm font-semibold text-slate-900">
        {{ title }}
      </h3>
      <p v-if="description" class="text-sm leading-6 text-slate-500">
        {{ description }}
      </p>
    </header>
    <div class="space-y-4">
      <slot />
    </div>
  </section>
</template>
