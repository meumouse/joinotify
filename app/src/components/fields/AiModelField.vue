<script setup>

/**
 * AiModelField.vue frontend component.
 *
 * Renders an AI default-model select plus a button that refreshes the available
 * models from the provider API, so newly released models appear without a plugin
 * update. The provider endpoint is read from `field.component_props.endpoint`
 * (defaults to OpenAI), which keeps the component reusable across providers.
 *
 * @since 2.0.0
 */
import { computed, inject, ref } from 'vue';
import SelectField from './SelectField.vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const api = inject('joinotifyApi', null);

const options = ref(Array.isArray(props.field.options) ? [...props.field.options] : []);
const loading = ref(false);
const errorMsg = ref('');

// Provider REST endpoint that returns the model list; defaults to OpenAI so the
// existing `openai-model-select` wiring keeps working without an endpoint.
const endpoint = computed(() => {
  const configured = props.field?.component_props?.endpoint;
  return typeof configured === 'string' && configured.trim() ? configured.trim() : 'admin/ai/openai-models';
});

const selectField = computed(() => ({
  ...props.field,
  options: options.value,
  searchable: true,
}));

async function refresh() {
  if (!api || loading.value) {
    return;
  }

  loading.value = true;
  errorMsg.value = '';

  try {
    const separator = endpoint.value.includes('?') ? '&' : '?';
    const res = await api.get(`${endpoint.value}${separator}refresh=1`);

    if (Array.isArray(res?.models)) {
      options.value = res.models;
    }

    if (res?.status === 'error' && res?.message) {
      errorMsg.value = res.message;
    }
  } catch (error) {
    errorMsg.value = error instanceof Error ? error.message : String(error);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-stretch gap-2">
      <div class="min-w-0 flex-1">
        <SelectField
          :field="selectField"
          :name="name"
          :disabled="disabled || loading"
          :model-value="modelValue"
          @update:model-value="emit('update:modelValue', $event)"
        />
      </div>

      <button
        v-if="api"
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-[8px] border border-slate-200 bg-white px-3 text-[13px] font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="loading"
        :title="__('Refresh models', textDomain)"
        @click="refresh"
      >
        <svg
          class="h-4 w-4 shrink-0"
          :class="loading ? 'animate-spin' : ''"
          viewBox="0 0 20 20"
          fill="none"
          aria-hidden="true"
        >
          <path d="M15.5 5.5A6.5 6.5 0 1 0 16.9 12" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
          <path d="M16 3v3h-3" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>{{ loading ? __('Updating…', textDomain) : __('Refresh', textDomain) }}</span>
      </button>
    </div>

    <p v-if="errorMsg" class="text-xs text-rose-600">{{ errorMsg }}</p>
  </div>
</template>
