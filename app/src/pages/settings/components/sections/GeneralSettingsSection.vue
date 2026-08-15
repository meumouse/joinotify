<script setup>

/**
 * GeneralSettingsSection.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../../utils/i18n';
import FieldRow from '../../../../components/fields/FieldRow.vue';
import FieldControl from '../../../../components/fields/FieldControl.vue';

defineProps({
  generalVisibleFields: { type: Array, default: () => [] },
  proxyToggleField: { type: Object, default: () => ({}) },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['update-setting', 'open-proxy-config']);
</script>

<template>
  <div class="space-y-2">
    <FieldRow
      v-for="field in generalVisibleFields"
      :key="field.key"
      :field="field"
      :name="field.key"
      :model-value="settings[field.key]"
      @update:model-value="$emit('update-setting', field.key, $event)"
    />

    <div class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
      <div>
        <h3 class="flex flex-wrap items-center gap-2 text-[15px] font-semibold text-slate-800">
          {{ __('Enable Proxy API', textDomain) }}
          <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-700">
            {{ __('Deprecated', textDomain) }}
          </span>
        </h3>
        <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
          {{ __('Turn this on to expose endpoints on this site for processing Joinotify API requests.', textDomain) }}
        </p>
      </div>

      <div class="flex items-center gap-4 lg:justify-self-start">
        <FieldControl :field="proxyToggleField" name="enable_proxy_api" :model-value="settings.enable_proxy_api" @update:model-value="$emit('update-setting', 'enable_proxy_api', $event)" />
        <button
          type="button"
          class="rounded-[8px] border border-primary-200 bg-white px-6 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50"
          @click="$emit('open-proxy-config')"
        >
          {{ __('Configure', textDomain) }}
        </button>
      </div>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-5 py-4 text-[13px] leading-5 text-amber-800">
      <strong class="font-semibold">{{ __('The Proxy API is deprecated and will be removed soon.', textDomain) }}</strong>
      {{ __('Sending moves exclusively to the Joinotify API, over the official WhatsApp Cloud API. Migrate any integration that still calls these endpoints, then turn this option off.', textDomain) }}
    </div>
  </div>
</template>
