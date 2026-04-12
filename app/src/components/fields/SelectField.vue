<template>
  <div ref="rootEl" class="relative">
    <button
      :id="buttonId"
      :name="name"
      type="button"
      class="flex w-full items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-left text-[14px] text-slate-700 outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-100 md:min-w-[330px]"
      :class="disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'hover:border-slate-300'"
      :aria-expanded="isOpen"
      :aria-haspopup="'listbox'"
      :aria-controls="listboxId"
      :disabled="disabled"
      @click="toggle"
      @keydown.down.prevent="openAndFocus(0)"
      @keydown.up.prevent="openAndFocus(options.length - 1)"
      @keydown.enter.prevent="toggle"
      @keydown.space.prevent="toggle"
      @keydown.esc.prevent="close"
    >
      <span class="min-w-0 flex-1 truncate" :class="selectedOption ? 'text-slate-800' : 'text-slate-400'">
        {{ selectedLabel }}
      </span>

      <svg class="h-4 w-4 shrink-0 text-slate-400 transition duration-150" :class="isOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div
        v-if="isOpen"
        :id="listboxId"
        class="absolute z-30 mt-2 w-full overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-[0_20px_45px_rgba(15,23,42,0.12)]"
      >
        <div v-if="showSearch" class="border-b border-slate-100 p-2">
          <input
            ref="searchEl"
            v-model="query"
            type="text"
            class="w-full rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2 text-[14px] text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:bg-white focus:ring-4 focus:ring-primary-100"
            :placeholder="field.searchPlaceholder || 'Search...'"
          />
        </div>

        <ul
          :id="listId"
          role="listbox"
          :aria-labelledby="buttonId"
          class="max-h-64 overflow-auto p-2"
          @keydown.down.prevent="moveActive(1)"
          @keydown.up.prevent="moveActive(-1)"
          @keydown.enter.prevent="commitActive"
          @keydown.esc.prevent="close"
        >
          <li
            v-if="showEmpty"
            class="rounded-[8px] px-3 py-2 text-[14px] text-slate-400"
          >
            {{ field.emptyLabel || 'No options available' }}
          </li>

          <li
            v-for="(option, index) in filteredOptions"
            :key="String(option.value)"
            :id="optionId(index)"
            role="option"
            :aria-selected="isSelected(option)"
            class="group flex cursor-pointer items-center justify-between gap-3 rounded-[8px] px-3 py-2 text-[14px] transition"
            :class="optionClass(index, option)"
            @mouseenter="activeIndex = index"
            @click="selectOption(option)"
          >
            <div class="min-w-0">
              <div class="truncate font-medium" :class="isSelected(option) ? 'text-primary-700' : 'text-slate-700'">
                {{ option.label }}
              </div>
              <div v-if="option.meta" class="truncate text-[12px] leading-5 text-slate-400">
                {{ option.meta }}
              </div>
            </div>

            <svg
              v-if="isSelected(option)"
              class="h-4 w-4 shrink-0 text-primary-700"
              viewBox="0 0 20 20"
              fill="none"
              aria-hidden="true"
            >
              <path d="M4.5 10.5L8 14L15.5 6.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </li>
        </ul>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  field: { type: Object, required: true },
  name: { type: String, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const rootEl = ref(null);
const searchEl = ref(null);
const isOpen = ref(false);
const query = ref('');
const activeIndex = ref(0);
const uid = `joinotify-select-${Math.random().toString(36).slice(2, 10)}`;

const options = computed(() => Array.isArray(props.field.options) ? props.field.options : []);
const showSearch = computed(() => Boolean(props.field.searchable) || options.value.length > 8);
const showEmpty = computed(() => filteredOptions.value.length === 0);

const filteredOptions = computed(() => {
  const list = options.value.map((option) => ({
    value: option.value,
    label: option.label,
    meta: option.meta || '',
  }));

  if (!query.value) {
    return list;
  }

  const needle = query.value.toLowerCase();

  return list.filter((option) => {
    return String(option.label || '').toLowerCase().includes(needle) || String(option.meta || '').toLowerCase().includes(needle);
  });
});

const selectedOption = computed(() => {
  return options.value.find((option) => String(option.value) === String(props.modelValue)) || null;
});

const selectedLabel = computed(() => {
  if (selectedOption.value) {
    return selectedOption.value.label;
  }

  return props.field.placeholder || 'Select an option';
});

const buttonId = `${uid}-button`;
const listboxId = `${uid}-listbox`;
const listId = `${uid}-list`;

watch(isOpen, (value) => {
  if (value) {
    query.value = '';
    activeIndex.value = Math.max(0, filteredOptions.value.findIndex((option) => isSelected(option)));

    window.requestAnimationFrame(() => {
      if (showSearch.value && searchEl.value) {
        searchEl.value.focus();
      }
    });
  }
});

watch(filteredOptions, (value) => {
  if (!value.length) {
    activeIndex.value = 0;
    return;
  }

  const selectedIndex = value.findIndex((option) => isSelected(option));
  activeIndex.value = selectedIndex >= 0 ? selectedIndex : 0;
});

function toggle() {
  if (props.disabled) {
    return;
  }

  isOpen.value = !isOpen.value;
}

function openAndFocus(index) {
  if (props.disabled) {
    return;
  }

  isOpen.value = true;
  activeIndex.value = normalizeIndex(index);
}

function close() {
  isOpen.value = false;
}

function normalizeIndex(index) {
  if (!filteredOptions.value.length) {
    return 0;
  }

  return Math.max(0, Math.min(index, filteredOptions.value.length - 1));
}

function isSelected(option) {
  return String(option.value) === String(props.modelValue);
}

function selectOption(option) {
  emit('update:modelValue', option.value);
  close();
}

function moveActive(delta) {
  if (!filteredOptions.value.length) {
    return;
  }

  activeIndex.value = normalizeIndex(activeIndex.value + delta);
}

function commitActive() {
  const option = filteredOptions.value[activeIndex.value];

  if (option) {
    selectOption(option);
  }
}

function optionId(index) {
  return `${uid}-option-${index}`;
}

function optionClass(index, option) {
  if (index === activeIndex.value) {
    return 'bg-primary-50 text-slate-800';
  }

  if (isSelected(option)) {
    return 'bg-primary-50/70';
  }

  return 'hover:bg-slate-50';
}

function handleOutsideClick(event) {
  if (!rootEl.value) {
    return;
  }

  if (!rootEl.value.contains(event.target)) {
    close();
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleOutsideClick);
});
</script>
