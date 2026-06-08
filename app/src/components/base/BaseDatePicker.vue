<script setup lang="ts">
/**
 * BaseDatePicker.vue
 *
 * Modern accessible date picker with a calendar popover. Mirrors the look and
 * floating/teleport behaviour of BaseListboxSelect so it can drop in for native
 * <input type="date"> across the app. Emits an ISO `YYYY-MM-DD` string (same
 * value shape the native date input produced) and renders a locale-aware label.
 *
 * @since 2.0.0
 */
import { computed, nextTick, ref, watch } from 'vue';
import { useElementBounding, onClickOutside } from '@vueuse/core';
import { Calendar, ChevronLeft, ChevronRight, X } from '@boxicons/vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true },
  min: { type: String, default: '' },
  max: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const locale = computed(
  () => globalThis?.navigator?.language || globalThis?.document?.documentElement?.lang || 'en'
);

/** Parse an ISO `YYYY-MM-DD` string into a local Date (no timezone shift). */
function parseISO(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

  if (!match) {
    return null;
  }

  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

  return Number.isNaN(date.getTime()) ? null : date;
}

/** Serialize a Date to an ISO `YYYY-MM-DD` string using local fields. */
function toISO(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${date.getFullYear()}-${month}-${day}`;
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

const today = new Date();
const selectedDate = computed(() => parseISO(props.modelValue));
const minDate = computed(() => parseISO(props.min));
const maxDate = computed(() => parseISO(props.max));

const displayValue = computed(() =>
  selectedDate.value
    ? new Intl.DateTimeFormat(locale.value, { day: '2-digit', month: 'short', year: 'numeric' }).format(
        selectedDate.value
      )
    : ''
);

// Month currently shown in the grid. Defaults to the selected month or today.
const viewYear = ref((selectedDate.value || today).getFullYear());
const viewMonth = ref((selectedDate.value || today).getMonth());

const monthLabel = computed(() =>
  new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(
    new Date(viewYear.value, viewMonth.value, 1)
  )
);

// Locale-aware short weekday headers, week starting on Sunday. 2023-01-01 was a Sunday.
const weekdayLabels = computed(() => {
  const formatter = new Intl.DateTimeFormat(locale.value, { weekday: 'short' });

  return Array.from({ length: 7 }, (_, index) => formatter.format(new Date(2023, 0, 1 + index)));
});

interface DayCell {
  date: Date;
  iso: string;
  day: number;
  inMonth: boolean;
  isToday: boolean;
  isSelected: boolean;
  disabled: boolean;
}

const weeks = computed<DayCell[][]>(() => {
  const firstOfMonth = new Date(viewYear.value, viewMonth.value, 1);
  const gridStart = new Date(viewYear.value, viewMonth.value, 1 - firstOfMonth.getDay());
  const cells: DayCell[] = [];

  for (let index = 0; index < 42; index += 1) {
    const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index);
    const belowMin = minDate.value ? date < minDate.value : false;
    const aboveMax = maxDate.value ? date > maxDate.value : false;

    cells.push({
      date,
      iso: toISO(date),
      day: date.getDate(),
      inMonth: date.getMonth() === viewMonth.value,
      isToday: isSameDay(date, today),
      isSelected: selectedDate.value ? isSameDay(date, selectedDate.value) : false,
      disabled: belowMin || aboveMax,
    });
  }

  return Array.from({ length: 6 }, (_, week) => cells.slice(week * 7, week * 7 + 7));
});

const isOpen = ref(false);
const anchorRef = ref<HTMLElement | null>(null);
const panelRef = ref<HTMLElement | null>(null);
const { x, width, bottom } = useElementBounding(anchorRef);
const floatingStyles = computed(() => ({
  position: 'fixed' as const,
  top: `${bottom.value + 4}px`,
  left: `${x.value}px`,
  minWidth: `${Math.max(width.value, 268)}px`,
}));

onClickOutside(
  panelRef,
  () => {
    isOpen.value = false;
  },
  { ignore: [anchorRef] }
);

function toggle() {
  if (props.disabled) {
    return;
  }

  if (!isOpen.value) {
    // Re-sync the grid to the selected month each time the popover opens.
    const focus = selectedDate.value || today;
    viewYear.value = focus.getFullYear();
    viewMonth.value = focus.getMonth();
  }

  isOpen.value = !isOpen.value;
}

function previousMonth() {
  const date = new Date(viewYear.value, viewMonth.value - 1, 1);
  viewYear.value = date.getFullYear();
  viewMonth.value = date.getMonth();
}

function nextMonth() {
  const date = new Date(viewYear.value, viewMonth.value + 1, 1);
  viewYear.value = date.getFullYear();
  viewMonth.value = date.getMonth();
}

function selectDay(cell: DayCell) {
  if (cell.disabled) {
    return;
  }

  emit('update:modelValue', cell.iso);
  emit('change', cell.iso);
  isOpen.value = false;
}

function clear(event: Event) {
  event.stopPropagation();
  emit('update:modelValue', '');
  emit('change', '');
}

// Keep the panel position fresh after it mounts into the teleport target.
watch(isOpen, (open) => {
  if (open) {
    nextTick();
  }
});
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
      {{ label }}
    </span>

    <div ref="anchorRef" class="relative">
      <button
        :id="id"
        :name="name"
        type="button"
        :disabled="disabled"
        class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-sm outline-none transition focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10 disabled:cursor-not-allowed disabled:bg-slate-50"
        :class="displayValue ? 'text-slate-700' : 'text-slate-400'"
        @click="toggle"
      >
        <span class="truncate">{{ displayValue || placeholder || __('Select a date', textDomain) }}</span>
        <span class="flex shrink-0 items-center gap-1">
          <X
            v-if="clearable && displayValue && !disabled"
            :width="15"
            :height="15"
            class="text-slate-400 transition hover:text-slate-600"
            role="button"
            :aria-label="__('Clear date', textDomain)"
            @click="clear"
          />
          <Calendar :width="16" :height="16" class="text-slate-400" />
        </span>
      </button>
    </div>

    <teleport to="body">
      <transition
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isOpen"
          ref="panelRef"
          :style="floatingStyles"
          class="z-[10000] rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
        >
          <!-- Month navigation -->
          <div class="mb-2 flex items-center justify-between">
            <button
              type="button"
              class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100"
              :aria-label="__('Previous month', textDomain)"
              @click="previousMonth"
            >
              <ChevronLeft :width="18" :height="18" />
            </button>
            <span class="text-sm font-semibold capitalize text-slate-700">{{ monthLabel }}</span>
            <button
              type="button"
              class="flex h-7 w-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100"
              :aria-label="__('Next month', textDomain)"
              @click="nextMonth"
            >
              <ChevronRight :width="18" :height="18" />
            </button>
          </div>

          <!-- Weekday headers -->
          <div class="grid grid-cols-7 gap-0.5">
            <span
              v-for="weekday in weekdayLabels"
              :key="weekday"
              class="flex h-7 items-center justify-center text-[11px] font-medium uppercase text-slate-400"
            >
              {{ weekday }}
            </span>
          </div>

          <!-- Day grid -->
          <div class="grid grid-cols-7 gap-0.5">
            <template v-for="(week, weekIndex) in weeks" :key="weekIndex">
              <button
                v-for="cell in week"
                :key="cell.iso"
                type="button"
                :disabled="cell.disabled"
                class="flex h-8 w-8 items-center justify-center rounded-md text-[13px] transition"
                :class="[
                  cell.isSelected
                    ? 'bg-primary-600 font-semibold text-white hover:bg-primary-600'
                    : cell.inMonth
                      ? 'text-slate-700 hover:bg-primary-50'
                      : 'text-slate-300 hover:bg-slate-50',
                  cell.isToday && !cell.isSelected ? 'ring-1 ring-inset ring-primary-300' : '',
                  cell.disabled ? 'cursor-not-allowed opacity-40 hover:bg-transparent' : '',
                ]"
                @click="selectDay(cell)"
              >
                {{ cell.day }}
              </button>
            </template>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>
