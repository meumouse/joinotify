<script setup>

/**
 * InputGroupField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed, useSlots } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  name: { type: String, required: true },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
  showHeader: { type: Boolean, default: true },
  prependText: { type: String, default: '' },
  appendText: { type: String, default: '' },
  inputClass: { type: [String, Array, Object], default: '' },
  groupClass: { type: [String, Array, Object], default: '' },
  addonClass: { type: [String, Array, Object], default: '' },
  wrapperClass: { type: [String, Array, Object], default: '' },
  autocomplete: { type: String, default: 'off' },
  inputmode: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const slots = useSlots();

const hasPrepend = computed(() => Boolean(slots.prepend) || Boolean(props.prependText));
const hasAppend = computed(() => Boolean(slots.append) || Boolean(slots.actions) || Boolean(props.appendText));

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});
</script>

<template>
  <label class="block" :class="wrapperClass">
    <template v-if="showHeader">
      <span v-if="label" class="text-sm font-medium text-ink">{{ label }}</span>
      <p v-if="description" class="mt-1 text-sm leading-6 text-muted">
        {{ description }}
      </p>
    </template>

    <div
      class="joinotify-input-group mt-2 flex overflow-hidden rounded-[10px] border border-slate-200 bg-white transition focus-within:border-primary-700 focus-within:ring-4 focus-within:ring-primary-100"
      :class="[disabled ? 'bg-slate-50' : '', groupClass]"
    >
      <div
        v-if="hasPrepend"
        class="joinotify-input-group__addon flex shrink-0 items-center border-r border-slate-200 bg-slate-50 px-4 text-[14px] font-medium text-slate-500"
        :class="addonClass"
      >
        <slot name="prepend">
          {{ prependText }}
        </slot>
      </div>

      <input
        :id="name"
        :name="name"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :inputmode="inputmode || undefined"
        class="joinotify-input-group__control min-w-0 flex-1 bg-transparent px-4 py-3 text-[14px] text-slate-700 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-50"
        :class="inputClass"
      />

      <div
        v-if="hasAppend"
        class="joinotify-input-group__actions flex shrink-0 items-stretch"
        :class="addonClass"
      >
        <slot name="append">
          <slot name="actions">
            {{ appendText }}
          </slot>
        </slot>
      </div>
    </div>
  </label>
</template>
