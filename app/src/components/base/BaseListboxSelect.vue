<script setup lang="ts">
/**
 * BaseListboxSelect.vue
 *
 * Modern accessible select built on @headlessui/vue Listbox. Drop-in
 * replacement for the native BaseSelect (same props/emits) used across the
 * builder so the action/trigger settings get a styled dropdown instead of the
 * default browser <select>.
 *
 * @since 2.0.0
 */
import { computed, ref } from 'vue';
import {
  Listbox,
  ListboxButton,
  ListboxOptions,
  ListboxOption,
} from '@headlessui/vue';
import { useElementBounding } from '@vueuse/core';
import { Check, ChevronDown } from '@boxicons/vue';
import { __, textDomain } from '../../utils/i18n';

interface SelectOption {
  label: string;
  value: string | number;
  disabled?: boolean;
}

const props = defineProps({
  modelValue: { type: [String, Number, Boolean], default: '' },
  options: { type: Array as () => SelectOption[], default: () => [] },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

// Anchor the dropdown to the button via fixed coordinates so the options can be
// teleported to <body> and escape any scroll container (e.g. the settings
// modal) that would otherwise clip them.
const anchorRef = ref<HTMLElement | null>(null);
const { x, width, bottom } = useElementBounding(anchorRef);
const floatingStyles = computed(() => ({
  position: 'fixed' as const,
  top: `${bottom.value + 4}px`,
  left: `${x.value}px`,
  width: `${width.value}px`,
}));

const selectedOption = computed(() =>
  props.options.find((option) => String(option.value) === String(props.modelValue)) || null
);

const buttonLabel = computed(() =>
  selectedOption.value
    ? selectedOption.value.label
    : props.placeholder || __('Select an option', textDomain)
);

function handleSelect(value: string | number) {
  emit('update:modelValue', value);
  emit('change', value);
}
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
      {{ label }}
    </span>

    <Listbox
      :model-value="modelValue"
      :disabled="disabled"
      as="div"
      class="relative"
      @update:model-value="handleSelect"
    >
      <div ref="anchorRef">
        <ListboxButton
          :id="id"
          :name="name"
          class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-sm text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
        >
          <span class="truncate" :class="{ 'text-slate-400': !selectedOption }">{{ buttonLabel }}</span>
          <ChevronDown :width="16" :height="16" class="shrink-0 text-slate-400" />
        </ListboxButton>
      </div>

      <teleport to="body">
        <transition
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <ListboxOptions
            :style="floatingStyles"
            class="z-[10000] max-h-60 overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-xl focus:outline-none"
          >
          <ListboxOption
            v-if="placeholder"
            v-slot="{ active, selected }"
            :value="''"
            as="template"
          >
            <li
              class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-slate-400"
              :class="active ? 'bg-primary-50 text-primary-800' : ''"
            >
              <span class="truncate">{{ placeholder }}</span>
              <Check v-if="selected" :width="15" :height="15" class="shrink-0 text-primary-600" />
            </li>
          </ListboxOption>

          <ListboxOption
            v-for="option in options"
            :key="String(option.value)"
            v-slot="{ active, selected }"
            :value="option.value"
            :disabled="option.disabled"
            as="template"
          >
            <li
              class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-slate-700"
              :class="[
                active ? 'bg-primary-50 text-primary-800' : '',
                option.disabled ? 'cursor-not-allowed opacity-50' : '',
              ]"
            >
              <span class="truncate" :class="selected ? 'font-semibold' : ''">{{ option.label }}</span>
              <Check v-if="selected" :width="15" :height="15" class="shrink-0 text-primary-600" />
            </li>
          </ListboxOption>
          </ListboxOptions>
        </transition>
      </teleport>
    </Listbox>
  </div>
</template>
