<script setup>

/**
 * AboutSettingsSection.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { __, textDomain } from '../../../../utils/i18n';
import FieldRow from '../../../../components/fields/FieldRow.vue';
import FieldControl from '../../../../components/fields/FieldControl.vue';
import SystemStatusPanel from '../cards/SystemStatusPanel.vue';
import DangerZone from '../cards/DangerZone.vue';

defineProps({
  aboutVisibleFields: { type: Array, default: () => [] },
  debugToggleField: { type: Object, default: () => ({}) },
  settings: { type: Object, default: () => ({}) },
  system: { type: Object, default: () => ({}) },
  version: { type: String, default: '' },
  updateState: { type: Object, default: () => ({}) },
});

defineEmits(['update-setting', 'open-logs', 'reset', 'clear-logs', 'check-updates']);
</script>

<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <FieldRow
        v-for="field in aboutVisibleFields"
        :key="field.key"
        :field="field"
        :name="field.key"
        :model-value="settings[field.key]"
        @update:model-value="$emit('update-setting', field.key, $event)"
      />
    </div>

    <div class="grid items-start gap-6 border-t border-slate-100 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
      <div>
        <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Plugin updates', textDomain) }}</h3>
        <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
          {{ __('You are currently using version', textDomain) }} <strong class="text-slate-700">{{ version }}</strong>.
          <span v-if="updateState.checked && updateState.available" class="font-medium text-emerald-600">
            {{ __('A new version is available:', textDomain) }} {{ updateState.latestVersion }}
          </span>
          <span v-else-if="updateState.checked" class="font-medium text-emerald-600">
            {{ __('Your plugin is up to date.', textDomain) }}
          </span>
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-4 lg:justify-self-start">
        <button
          type="button"
          class="rounded-[8px] border border-primary-200 bg-white px-6 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="updateState.loading"
          @click="$emit('check-updates')"
        >
          {{ updateState.loading ? __('Checking…', textDomain) : __('Check for updates', textDomain) }}
        </button>
        <a
          v-if="updateState.checked && updateState.available && updateState.updateUrl"
          :href="updateState.updateUrl"
          class="rounded-[8px] bg-primary-600 px-6 py-3 text-[14px] font-semibold text-white transition hover:bg-primary-700"
        >
          {{ __('Update now', textDomain) }}
        </a>
      </div>
    </div>

    <div class="grid items-start gap-6 py-6 lg:grid-cols-[minmax(0,420px)_minmax(0,460px)] lg:items-center">
      <div>
        <h3 class="text-[15px] font-semibold text-slate-800">{{ __('Enable debug mode', textDomain) }}</h3>
        <p class="mt-1 max-w-xl text-[13px] leading-5 text-slate-500">
          {{ __('Turn this on to enable debug mode and inspect errors and other relevant information.', textDomain) }}
        </p>
      </div>

      <div class="flex items-center gap-4 lg:justify-self-start">
        <FieldControl :field="debugToggleField" name="enable_debug_mode" :model-value="settings.enable_debug_mode" @update:model-value="$emit('update-setting', 'enable_debug_mode', $event)" />
        <button
          type="button"
          class="rounded-[8px] border border-primary-200 bg-white px-6 py-3 text-[14px] font-semibold text-primary-700 transition hover:bg-primary-50"
          @click="$emit('open-logs')"
        >
          {{ __('View debug logs', textDomain) }}
        </button>
      </div>
    </div>

    <div class="mt-6">
      <SystemStatusPanel :system="system" />
    </div>

    <div class="mt-8">
      <DangerZone @reset="$emit('reset')" @clear-logs="$emit('clear-logs')" />
    </div>
  </div>
</template>
