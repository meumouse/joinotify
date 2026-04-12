<template>
  <div class="flex h-full flex-col rounded-[10px] border border-slate-200 bg-white">
    <div class="flex min-h-[155px] items-center justify-center border-b border-slate-200 px-6 py-8">
      <div class="text-center">
        <div
          v-if="card.icon"
          class="mx-auto flex h-24 w-24 items-center justify-center"
          v-html="card.icon"
        />
        <div v-else class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-slate-100 text-4xl text-slate-400">
          {{ initial }}
        </div>
      </div>
    </div>

    <div class="flex flex-1 flex-col px-6 py-6 text-center">
      <h3 class="text-[22px] font-semibold leading-7 text-slate-700">{{ card.title }}</h3>
      <p v-if="card.description" class="mt-5 text-[14px] leading-6 text-slate-500">
        {{ card.description }}
      </p>

      <div class="mt-5 flex flex-1 flex-col items-center justify-end gap-4">
        <ToggleSwitch
          :id="`integration-${card.slug}`"
          :aria-label="`Alternar ${card.title}`"
          size="md"
          :disabled="card.requires_plugin && !card.plugin_active"
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

<script setup>
import { computed } from 'vue';
import ToggleSwitch from '../base/ToggleSwitch.vue';

const props = defineProps({
  card: { type: Object, required: true },
  enabled: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'configure']);

const initial = computed(() => (props.card.title ? props.card.title.charAt(0).toUpperCase() : 'I'));
const showConfigButton = computed(() => Array.isArray(props.card.fields) && props.card.fields.length > 0);
const configLabel = computed(() => (props.card.slug === 'woocommerce' ? 'Configurações' : 'Configurar'));
const enabledProxy = computed({
  get: () => props.enabled,
  set: () => {
    if (!props.card.requires_plugin || props.card.plugin_active) {
      emit('toggle');
    }
  },
});
</script>
