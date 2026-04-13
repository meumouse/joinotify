<script setup>

/**
 * InputGroupField.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.8
 */
import { computed, useSlots } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean, Object, Array], default: '' },
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
  items: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'action']);

const slots = useSlots();

const compositeItems = computed(() => (Array.isArray(props.items) ? props.items.filter(Boolean) : []));
const usesCompositeGroup = computed(() => compositeItems.value.length > 0);

const hasPrepend = computed(() => Boolean(slots.prepend) || Boolean(props.prependText));
const hasAppend = computed(() => Boolean(slots.append) || Boolean(slots.actions) || Boolean(props.appendText));

const model = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function normalizeItemType(item) {
  const type = String(item?.type || 'text').trim().toLowerCase();

  if (type === 'input') {
    return 'text';
  }

  if (['text', 'select', 'button', 'addon'].includes(type)) {
    return type;
  }

  return 'text';
}

function cloneGroupValue() {
  if (isPlainObject(props.modelValue) || Array.isArray(props.modelValue)) {
    return { ...props.modelValue };
  }

  return {
    value: props.modelValue ?? '',
  };
}

function normalizeItemKey(item, index) {
  const rawKey = item?.key || item?.name || item?.modelKey || item?.field || '';

  if (rawKey) {
    return String(rawKey);
  }

  if (normalizeItemType(item) === 'button' || normalizeItemType(item) === 'addon') {
    return `item-${index}`;
  }

  return `value-${index}`;
}

function readItemValue(item, index) {
  const key = normalizeItemKey(item, index);
  const groupValue = cloneGroupValue();

  if (Object.prototype.hasOwnProperty.call(groupValue, key)) {
    return groupValue[key];
  }

  if (item && Object.prototype.hasOwnProperty.call(item, 'default')) {
    return item.default;
  }

  if (item && Object.prototype.hasOwnProperty.call(item, 'value') && normalizeItemType(item) !== 'button' && normalizeItemType(item) !== 'addon') {
    return item.value;
  }

  return '';
}

function writeItemValue(item, index, value) {
  const key = normalizeItemKey(item, index);
  const nextValue = cloneGroupValue();
  nextValue[key] = value;
  emit('update:modelValue', nextValue);
}

async function copyToClipboard(value) {
  const text = String(value ?? '');

  if (!text) {
    return;
  }

  if (navigator?.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', 'true');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}

function resolveButtonSource(item, index) {
  if (item?.source) {
    return readItemValue({ key: item.source }, index);
  }

  if (item?.target) {
    return readItemValue({ key: item.target }, index);
  }

  if (item?.key) {
    return readItemValue(item, index);
  }

  return item?.value ?? '';
}

async function handleButtonClick(item, index) {
  const action = String(item?.action || '').trim().toLowerCase();

  if (action === 'copy') {
    await copyToClipboard(resolveButtonSource(item, index));
  } else if (action === 'set') {
    const target = item?.target || item?.key;

    if (target) {
      writeItemValue({ ...item, key: target }, index, item?.value ?? '');
    }
  } else if (action === 'clear') {
    const target = item?.target || item?.key;

    if (target) {
      writeItemValue({ ...item, key: target }, index, item?.clearValue ?? '');
    }
  } else {
    emit('action', {
      item,
      index,
      value: cloneGroupValue(),
    });
  }
}

function itemControlClass(item, index) {
  const classes = [
    'min-w-0',
    'border-0',
    'bg-transparent',
    'px-4',
    'py-3',
    'text-[14px]',
    'text-slate-700',
    'outline-none',
    'placeholder:text-slate-400',
    'disabled:cursor-not-allowed',
    'disabled:bg-slate-50',
  ];

  if (normalizeItemType(item) === 'select') {
    classes.push('appearance-none', 'pr-10');
  }

  if (index === 0) {
    classes.push('rounded-l-[8px]');
  }

  if (index === compositeItems.value.length - 1) {
    classes.push('rounded-r-[8px]');
  }

  return classes.join(' ');
}

function itemShellClass(item, index) {
  const base = [
    'flex',
    'shrink-0',
    'items-stretch',
    'border-r',
    'border-slate-200',
    'bg-white',
  ];

  if (normalizeItemType(item) === 'button') {
    base.push('p-0');
  } else if (normalizeItemType(item) === 'addon') {
    base.push('px-4', 'py-3', 'text-[14px]', 'font-medium', 'text-slate-500');
  } else {
    base.push('min-w-[160px]');
  }

  if (index === compositeItems.value.length - 1) {
    base.push('border-r-0');
  }

  return base.join(' ');
}

function itemWrapperClass(item) {
  return [item?.class || item?.itemClass || ''].filter(Boolean).join(' ');
}

function itemWrapperStyle(item) {
  if (!item?.width) {
    return undefined;
  }

  return {
    flexBasis: item.width,
  };
}
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
      v-if="!usesCompositeGroup"
      class="joinotify-input-group mt-2 flex overflow-hidden rounded-lg border border-slate-200 bg-white transition focus-within:border-primary-700 focus-within:ring-4 focus-within:ring-primary-100"
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

    <div
      v-else
      class="joinotify-input-group mt-2 flex w-full flex-wrap overflow-hidden rounded-lg border border-slate-200 bg-white transition focus-within:border-primary-700 focus-within:ring-4 focus-within:ring-primary-100"
      :class="[disabled ? 'bg-slate-50' : '', groupClass]"
    >
      <div
        v-for="(item, index) in compositeItems"
        :key="`${name}-${normalizeItemKey(item, index)}`"
        :class="[itemShellClass(item, index), itemWrapperClass(item)]"
        :style="itemWrapperStyle(item)"
      >
        <template v-if="normalizeItemType(item) === 'addon'">
          {{ item.label || item.text || item.value || '' }}
        </template>

        <button
          v-else-if="normalizeItemType(item) === 'button'"
          type="button"
          class="inline-flex min-h-[48px] items-center justify-center border-0 border-l border-slate-200 bg-white px-4 text-[14px] font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
          :class="[item.class || item.buttonClass || '', addonClass]"
          :disabled="disabled || item.disabled"
          @click="handleButtonClick(item, index)"
        >
          {{ item.label || item.text || item.value || '' }}
        </button>

        <select
          v-else-if="normalizeItemType(item) === 'select'"
          :name="item.name || normalizeItemKey(item, index)"
          :value="readItemValue(item, index)"
          :disabled="disabled || item.disabled"
          class="joinotify-input-group__control min-w-0 flex-1 bg-transparent px-4 py-3 text-[14px] text-slate-700 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-50"
          :class="[itemControlClass(item, index), item.inputClass || '']"
          @change="writeItemValue(item, index, $event.target.value)"
        >
          <option
            v-for="option in Array.isArray(item.options) ? item.options : []"
            :key="String(option.value)"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>

        <input
          v-else
          :name="item.name || normalizeItemKey(item, index)"
          :value="readItemValue(item, index)"
          :type="item.inputType || (normalizeItemType(item) === 'text' ? type : normalizeItemType(item))"
          :placeholder="item.placeholder || ''"
          :disabled="disabled || item.disabled"
          :autocomplete="item.autocomplete || autocomplete"
          :inputmode="item.inputmode || undefined"
          class="joinotify-input-group__control min-w-0 flex-1 bg-transparent px-4 py-3 text-[14px] text-slate-700 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-50"
          :class="[itemControlClass(item, index), item.inputClass || '']"
          @input="writeItemValue(item, index, $event.target.value)"
        />
      </div>
    </div>
  </label>
</template>
