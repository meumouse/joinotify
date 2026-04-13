<script setup>
import { __, textDomain } from '../../utils/i18n';
import BaseCheckbox from '../buttons/checkbox/BaseCheckbox.vue';

defineProps({
  allSelected: { type: Boolean, default: false },
  indeterminate: { type: Boolean, default: false },
  selectable: { type: Boolean, default: true },
});

defineEmits(['toggleAll']);
</script>

<template>
  <thead class="bg-shell-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-shell-500">
    <tr>
      <th class="w-12 px-4 py-3">
        <BaseCheckbox
          :model-value="allSelected"
          :indeterminate="indeterminate"
          :aria-label="__('Select all visible workflows', textDomain)"
          :disabled="!selectable"
          @change="$emit('toggleAll', $event)"
        />
      </th>
      <th class="px-4 py-3">{{ __('Name', textDomain) }}</th>
      <th class="px-4 py-3">{{ __('Created', textDomain) }}</th>
      <th class="px-4 py-3">{{ __('Status', textDomain) }}</th>
    </tr>
  </thead>
</template>
