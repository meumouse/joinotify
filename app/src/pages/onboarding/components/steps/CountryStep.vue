<script setup>

/**
 * CountryStep.vue frontend component.
 *
 * Picks the dial code used to complete phone numbers typed without one. The
 * backend already guessed it from the WooCommerce store address, the site
 * locale or the timezone, so this step is usually a confirmation.
 *
 * @since 2.3.0
 */
import { computed } from 'vue';
import { __, sprintf, textDomain } from '../../../../utils/i18n';
import SelectField from '../../../../components/fields/SelectField.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  suggestion: { type: Object, default: () => ({}) },
});

defineEmits(['update:modelValue']);

const field = computed(() => ({
  label: __('Default country code', textDomain),
  options: props.options,
  searchable: true,
  searchPlaceholder: __('Search for a country', textDomain),
  emptyLabel: __('No country matches that search.', textDomain),
}));

const suggestionLabel = computed(() => {
  const option = props.options.find((entry) => String(entry.value) === String(props.suggestion?.code));

  return option ? option.label : '';
});

const suggestionNote = computed(() => {
  const label = suggestionLabel.value;

  if (!label) {
    return '';
  }

  const notes = {
    woocommerce: __('We pre-selected %s because that is your WooCommerce store address.', textDomain),
    locale: __('We pre-selected %s based on this site’s language setting.', textDomain),
    timezone: __('We pre-selected %s based on this site’s timezone.', textDomain),
  };

  const note = notes[props.suggestion?.source];

  return note ? sprintf(note, label) : '';
});
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('When a phone number reaches a workflow without a country code, Joinotify completes it with the country you choose here. Numbers that already carry a country code are never changed.', textDomain) }}
    </p>

    <div class="max-w-md">
      <p class="mb-2 text-[14px] font-medium text-slate-700">
        {{ __('Default country code', textDomain) }}
      </p>

      <SelectField
        :field="field"
        name="joinotify_default_country_code"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
      />

      <p v-if="suggestionNote" class="mt-3 text-[13px] leading-5 text-shell-500">
        {{ suggestionNote }}
      </p>
    </div>
  </div>
</template>
