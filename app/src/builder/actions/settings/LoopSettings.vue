<script setup lang="ts">
/**
 * LoopSettings.vue
 *
 * Settings panel for the "Loop" action. Chooses the collection to iterate over
 * (order digital files, order purchased items or a delimited placeholder list),
 * and — for the list source — the list value and its delimiter. An optional cap
 * limits how many items are processed. The actions nested in the loop body run
 * once per item and can reference it through {{ loop_* }} placeholders.
 *
 * @since 2.1.1
 */
import { computed } from 'vue';
import BaseSelectField from '../../components/base/BaseSelectField.vue';
import BaseTextField from '../../components/base/BaseTextField.vue';
import BaseRichTextArea from '../../../components/base/BaseRichTextArea.vue';
import FieldGroup from '../../components/base/FieldGroup.vue';
import { useActionSettingsUpdate } from '../../../composables/useActionSettingsUpdate';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  availablePlaceholders: { type: Array, default: () => [] },
  cronAvailable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'placeholder-selected']);

const { update } = useActionSettingsUpdate(props, emit);

const sourceOptions = [
  { label: __('Order digital files', textDomain), value: 'order_downloads' },
  { label: __('Order purchased items', textDomain), value: 'order_items' },
  { label: __('List from a placeholder', textDomain), value: 'placeholder_list' },
];

const source = computed(() => String((props.modelValue as Record<string, unknown>).loop_source || 'order_downloads'));
const isList = computed(() => source.value === 'placeholder_list');

// Kept in the script (not inline in the template) because the literal {{ }} tokens
// would otherwise be parsed as nested Vue interpolations.
const loopTokensHint = __('Inside the loop body you can use {{ loop_value }}, {{ loop_number }}, {{ loop_count }} and, for digital files, {{ loop_file_name }} and {{ loop_download_url }}.', textDomain);

const sourceHint = computed(() => {
  if (source.value === 'order_items') {
    return __('Runs the body once for each product line in the order.', textDomain);
  }

  if (source.value === 'placeholder_list') {
    return __('Runs the body once for each entry of the list below.', textDomain);
  }

  return __('Runs the body once for each downloadable file granted in the order. Attach {{ loop item }} files inside the body to send one file per message.', textDomain);
});
</script>

<template>
  <div class="space-y-4">
    <FieldGroup :title="__('Collection', textDomain)" :description="sourceHint">
      <BaseSelectField
        :model-value="source"
        :options="sourceOptions"
        :label="__('Iterate over', textDomain)"
        @update:model-value="update('loop_source', $event)"
      />
    </FieldGroup>

    <template v-if="isList">
      <FieldGroup :title="__('List', textDomain)" :description="__('The value to iterate. Use {{ placeholders }} — each entry becomes one iteration.', textDomain)">
        <BaseRichTextArea
          :model-value="String(modelValue.loop_list || '')"
          :label="__('List', textDomain)"
          :rows="4"
          :placeholder="__('One entry per line, or use the delimiter below', textDomain)"
          :placeholders="availablePlaceholders"
          @update:model-value="update('loop_list', $event)"
        />
      </FieldGroup>

      <FieldGroup :title="__('Delimiter', textDomain)" :description="__('Character that separates the entries. Leave empty to split by line break.', textDomain)">
        <BaseTextField
          :model-value="String(modelValue.loop_delimiter || '')"
          :label="__('Delimiter', textDomain)"
          :placeholder="__('e.g. , (comma)', textDomain)"
          @update:model-value="update('loop_delimiter', $event)"
        />
      </FieldGroup>
    </template>

    <FieldGroup :title="__('Maximum items', textDomain)" :description="__('Optional cap on how many items are processed. Leave empty for no limit.', textDomain)">
      <BaseTextField
        :model-value="String(modelValue.loop_max_items ?? '')"
        :label="__('Maximum items', textDomain)"
        type="number"
        placeholder="0"
        @update:model-value="update('loop_max_items', $event)"
      />
    </FieldGroup>

    <p class="rounded-lg bg-indigo-50 px-3 py-2 text-xs leading-5 text-indigo-700">
      {{ loopTokensHint }}
    </p>
  </div>
</template>
