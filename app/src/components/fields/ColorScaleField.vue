<script setup>
/**
 * ColorScaleField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { __, sprintf, textDomain } from '../../utils/i18n';
import { SHADE_STEPS, generatePalette, normalizeHex } from '../../utils/color';

const props = defineProps({
  modelValue: { type: Object, default: null },
  baseColor: { type: String, default: '#4f46e5' },
  palette: { type: Object, default: () => ({}) },
  baseColorName: { type: String, required: true },
  paletteName: { type: String, required: true },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  showHeader: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:baseColor', 'update:palette']);

const currentBaseColor = computed(() => {
  if (props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)) {
    const hasBaseColor = Object.prototype.hasOwnProperty.call(props.modelValue, 'baseColor')
      || Object.prototype.hasOwnProperty.call(props.modelValue, 'base_color');

    if (hasBaseColor) {
      return normalizeHex(props.modelValue.baseColor) || normalizeHex(props.modelValue.base_color) || '';
    }
  }

  return normalizeHex(props.baseColor) || '';
});

const currentPalette = computed(() => {
  if (props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)) {
    const hasPalette = Object.prototype.hasOwnProperty.call(props.modelValue, 'palette')
      || Object.prototype.hasOwnProperty.call(props.modelValue, 'colorScale');

    if (hasPalette) {
      return props.modelValue.palette || props.modelValue.colorScale || {};
    }
  }

  return props.palette || {};
});

const derivedPalette = computed(() => generatePalette(currentBaseColor.value || '#4f46e5'));

function isObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function syncModel(nextBaseColor, nextPalette) {
  const nextValue = {
    ...(isObject(props.modelValue) ? props.modelValue : {}),
    baseColor: nextBaseColor,
    palette: nextPalette,
  };

  emit('update:modelValue', nextValue);
  emit('update:baseColor', nextBaseColor);
  emit('update:palette', nextPalette);
}

function updateBaseColor(value) {
  const normalized = normalizeHex(value);

  if (!normalized) {
    return;
  }

  syncModel(normalized, { ...generatePalette(normalized) });
}

function updateShade(step, value) {
  const normalized = normalizeHex(value);

  if (!normalized) {
    return;
  }

  syncModel(currentBaseColor.value, {
    ...currentPalette.value,
    [step]: normalized,
  });
}

function clearShade(step) {
  if (props.disabled) {
    return;
  }

  const nextPalette = { ...currentPalette.value };
  delete nextPalette[step];

  syncModel(currentBaseColor.value, nextPalette);
}

function clearColors() {
  if (props.disabled) {
    return;
  }

  syncModel('', {});
}

function shadeValue(step) {
  return currentPalette.value[step] || derivedPalette.value[step] || '#ffffff';
}
</script>

<template>
  <div class="grid gap-4">
    <div v-if="showHeader" class="space-y-1">
      <h3 class="text-sm font-semibold text-slate-800">
        {{ label }}
      </h3>

      <p v-if="description" class="text-xs leading-5 text-slate-500">
        {{ description }}
      </p>
    </div>

    <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-[0_1px_0_rgba(15,23,42,0.02)]">
      <div class="grid gap-3 sm:grid-cols-[92px_minmax(0,1fr)]">
        <div class="flex items-center gap-3 sm:flex-col sm:items-start sm:justify-center">
          <span class="text-sm font-medium text-slate-700">
            {{ __('Base', textDomain) }}
          </span>

          <button
            :style="{ backgroundColor: currentBaseColor || '#4f46e5' }"
            class="relative h-10 w-10 shrink-0 rounded-xl border border-slate-200 shadow-sm transition hover:scale-[1.02] disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="disabled"
            :aria-label="__('Pick base color', textDomain)"
          >
            <input
              :value="currentBaseColor || '#4f46e5'"
              class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
              type="color"
              :disabled="disabled"
              @input="updateBaseColor($event.target.value)"
              @change="updateBaseColor($event.target.value)"
            >
          </button>
        </div>

        <div class="grid gap-3">
          <div class="flex flex-wrap items-center gap-3">
            <input
              :value="currentBaseColor"
              class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:bg-white focus:ring-4 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50"
              :disabled="disabled"
              placeholder="#4f46e5"
              type="text"
              @input="updateBaseColor($event.target.value)"
            >

            <button
              v-if="currentBaseColor || Object.keys(currentPalette).length"
              class="rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm font-medium text-amber-700 transition hover:border-amber-300 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="disabled"
              @click="clearColors"
            >
              {{ __('Clear', textDomain) }}
            </button>
          </div>

          <p class="text-xs leading-5 text-slate-500">
            {{ __('Changing the base color regenerates the palette. Individual shades can still be overridden.', textDomain) }}
          </p>
        </div>
      </div>

      <div class="grid gap-2">
        <div
          v-for="step in SHADE_STEPS"
          :key="step"
          class="grid grid-cols-[92px_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5"
        >
          <span class="text-sm font-medium text-slate-700">
            {{ step === '0' ? __('Base', textDomain) : sprintf(__('Shade %s', textDomain), step) }}
          </span>

          <div class="flex min-w-0 items-center gap-3">
            <button
              :style="{ backgroundColor: shadeValue(step) }"
              class="relative h-9 w-9 shrink-0 rounded-lg border border-slate-200 shadow-sm transition hover:scale-[1.02] disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="disabled"
              :aria-label="step === '0' ? __('Pick base shade', textDomain) : sprintf(__('Pick shade %s', textDomain), step)"
            >
              <input
                :value="shadeValue(step)"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                type="color"
                :disabled="disabled"
                @input="updateShade(step, $event.target.value)"
                @change="updateShade(step, $event.target.value)"
              >
            </button>

            <input
              :value="shadeValue(step)"
              class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50"
              readonly
              type="text"
            >
          </div>

          <button
            class="rounded-lg border border-transparent px-2 py-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
            type="button"
            :disabled="disabled || !currentPalette[step]"
            :aria-label="step === '0' ? __('Reset base shade override', textDomain) : sprintf(__('Clear shade %s', textDomain), step)"
            @click="clearShade(step)"
          >
            <span aria-hidden="true">x</span>
          </button>
        </div>
      </div>

      <input :name="baseColorName" :value="currentBaseColor" type="hidden">
      <template v-for="step in SHADE_STEPS" :key="`palette-${step}`">
        <input
          :name="`${paletteName}[${step}]`"
          :value="shadeValue(step)"
          type="hidden"
        >
      </template>
    </div>
  </div>
</template>
