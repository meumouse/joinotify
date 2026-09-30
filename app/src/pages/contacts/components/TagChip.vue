<script setup>
/**
 * TagChip.vue — a contact tag in its palette color, optionally removable.
 *
 * @since 2.5.0
 */
import { X } from '@boxicons/vue';
import { __, textDomain } from '../../../utils/i18n';
import { tagClass } from '../labels';

defineProps({
  name: { type: String, required: true },
  color: { type: String, default: '' },
  removable: { type: Boolean, default: false },
});

defineEmits(['remove']);
</script>

<template>
  <span class="inline-flex max-w-full items-center gap-1 rounded-full px-2.5 py-0.5 text-[12px] font-medium ring-1 ring-inset" :class="tagClass(color)">
    <span class="truncate">{{ name }}</span>
    <button
      v-if="removable"
      type="button"
      class="-mr-1 rounded-full p-0.5 opacity-60 transition hover:opacity-100"
      :aria-label="`${__('Remove tag', textDomain)} ${name}`"
      @click.stop="$emit('remove')"
    >
      <X class="h-3 w-3" aria-hidden="true" />
    </button>
  </span>
</template>
