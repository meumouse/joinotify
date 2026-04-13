<script setup>

/**
 * IntegrationCard.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { __, sprintf, textDomain } from '../../../../utils/i18n';
import ToggleSwitch from '../../../../components/toggles/ToggleSwitch.vue';

const props = defineProps({
  card: { type: Object, required: true },
  enabled: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'configure']);

const hasSettings = computed(() => Array.isArray(props.card.settings) ? props.card.settings.length > 0 : Array.isArray(props.card.fields) && props.card.fields.length > 0);
const toggleDisabled = computed(() => Boolean(props.card.coming_soon || props.card.comming_soon) || (props.card.requires_plugin && !props.card.plugin_active));
const configLabel = computed(() => props.card?.modal?.button_label || __('Configure', textDomain));
const showConfigButton = computed(() => hasSettings.value && props.enabled && !toggleDisabled.value);
const enabledProxy = computed({
  get: () => props.enabled,
  set: () => {
    if (!toggleDisabled.value) {
      emit('toggle');
    }
  },
});
</script>

<template>
  <div class="flex h-full flex-col rounded-[10px] border border-slate-200 bg-white">
    <div class="flex min-h-[155px] items-center justify-center border-b border-slate-200 px-6 py-8">
      <div class="text-center">
        <div
          v-if="card.icon"
          class="mx-auto flex h-20 w-20 items-center justify-center"
          v-html="card.icon"
        />
        <div v-else class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-4xl text-slate-400">
          {{ card.title ? card.title.charAt(0).toUpperCase() : 'I' }}
        </div>
      </div>
    </div>

    <div class="flex flex-1 flex-col px-6 py-6 text-center">
      <h3 class="text-[22px] font-semibold leading-7 text-slate-700">{{ card.title }}</h3>
      <p v-if="card.description" class="mt-5 text-[14px] leading-6 text-slate-500">
        {{ card.description }}
      </p>

      <div class="mt-5 space-y-3 text-left">
        <div
          v-if="card.coming_soon || card.comming_soon"
          class="rounded-lg border border-primary-100 bg-primary-50 px-4 py-3 text-[13px] font-medium text-primary-700"
        >
          {{ __('Coming soon', textDomain) }}
        </div>

        <div
          v-if="card.coming_soon || card.comming_soon"
          class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] leading-5 text-amber-800"
        >
          {{ card.disabled_message || __('This integration depends on an installed and active plugin.', textDomain) }}
        </div>

        <div
          v-if="!toggleDisabled && hasSettings && !enabled"
          class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-5 text-slate-600"
        >
          {{ __('Enable the integration to access its settings.', textDomain) }}
        </div>
      </div>

      <div class="mt-5 flex flex-1 flex-col items-center justify-end gap-4">
        <ToggleSwitch
          :id="`integration-${card.slug}`"
          :aria-label="sprintf(__('Toggle %s', textDomain), card.title || '')"
          size="md"
          :disabled="toggleDisabled"
          v-model="enabledProxy"
        />

        <button
          v-if="showConfigButton"
          type="button"
          class="rounded-[8px] border border-primary-200 px-6 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50"
          @click="$emit('configure', card.slug)"
        >
          {{ configLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
