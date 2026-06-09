<script setup>

/**
 * ImportExportSettings.vue frontend component.
 *
 * Lets the user download the plugin configuration as a JSON file and import a
 * previously exported file back, merging it into the current settings.
 *
 * @since 2.0.0
 * @version 2.0.0
 */
import { ref } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';

const props = defineProps({
  exporting: { type: Boolean, default: false },
  importing: { type: Boolean, default: false },
});

const emit = defineEmits(['export', 'import', 'error']);

const fileInput = ref(null);

function pickFile() {
  if (props.importing) {
    return;
  }

  fileInput.value?.click();
}

function onFileSelected(event) {
  const file = event.target?.files?.[0];

  // reset so selecting the same file again still triggers change
  event.target.value = '';

  if (!file) {
    return;
  }

  const reader = new FileReader();

  reader.onload = () => {
    try {
      const payload = JSON.parse(String(reader.result || ''));
      emit('import', payload);
    } catch (error) {
      emit('error', __('Invalid file. Could not read the configuration.', textDomain));
    }
  };

  reader.onerror = () => {
    emit('error', __('Could not read the selected file.', textDomain));
  };

  reader.readAsText(file);
}
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h3 class="text-base font-semibold text-slate-800">{{ __('Import / export settings', textDomain) }}</h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">
          {{ __('Download the plugin configuration as a JSON file or import it on another site. API keys and license data are not included in the export.', textDomain) }}
        </p>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      <button
        type="button"
        class="rounded-full bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="exporting"
        @click="$emit('export')"
      >
        {{ exporting ? __('Exporting…', textDomain) : __('Export settings', textDomain) }}
      </button>
      <button
        type="button"
        class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="importing"
        @click="pickFile"
      >
        {{ importing ? __('Importing…', textDomain) : __('Import settings', textDomain) }}
      </button>
      <input
        ref="fileInput"
        type="file"
        accept="application/json,.json"
        class="hidden"
        @change="onFileSelected"
      />
    </div>
  </div>
</template>
