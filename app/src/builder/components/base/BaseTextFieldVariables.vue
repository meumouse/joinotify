<script setup lang="ts">
/**
 * BaseTextFieldVariables.vue
 *
 * Single-line text field with a trailing variable picker, for fields that
 * support placeholder substitution (e.g. the WhatsApp recipient). Registered
 * placeholders ({{ ... }}) are highlighted in blue directly inside the field
 * via a mirrored overlay, and reveal their sandbox sample in a tooltip on
 * hover. The chosen variable is inserted at the caret position.
 *
 * @since 2.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import VariablePicker from '../../../components/base/VariablePicker.vue';
import { escapeHtml } from '../../../utils/html';
import { __, textDomain } from '../../../utils/i18n';

interface PlaceholderItem {
  placeholder: string;
  description?: string;
  replacement?: Record<string, unknown>;
}

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  disabled: { type: Boolean, default: false },
  placeholders: { type: Array as () => Array<PlaceholderItem | string>, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

const inputRef = ref<HTMLInputElement | null>(null);
const mirrorRef = ref<HTMLElement | null>(null);
const selection = ref({ start: 0, end: 0 });

/**
 * Normalize a placeholder token into a comparable key: strip the outer braces,
 * collapse inner whitespace and lowercase it.
 */
function normalizeKey(placeholder: string): string {
  return String(placeholder || '')
    .replace(/^\s*\{\{\s*/, '')
    .replace(/\s*\}\}\s*$/, '')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

const lookup = computed(() => {
  const map = new Map<string, string>();

  for (const raw of Array.isArray(props.placeholders) ? props.placeholders : []) {
    const item = typeof raw === 'string' ? { placeholder: raw } : raw;

    if (!item || !item.placeholder) {
      continue;
    }

    const replacement =
      item.replacement && typeof item.replacement === 'object'
        ? (item.replacement as Record<string, unknown>)
        : {};
    const sandbox = typeof replacement.sandbox === 'string' ? replacement.sandbox.trim() : '';
    const description = typeof item.description === 'string' ? item.description.trim() : '';

    const parts: string[] = [];

    if (description) {
      parts.push(description);
    }

    if (sandbox) {
      parts.push(`${__('Example', textDomain)}: ${sandbox}`);
    }

    map.set(normalizeKey(item.placeholder), parts.join(' — '));
  }

  return map;
});

/**
 * Mirrored markup rendered on top of the input. Variables get the same blue
 * background chip used by the message field, but without bold/padding so glyph
 * widths stay identical to the underlying input text and the caret keeps aligned.
 */
const mirrorHtml = computed(() => {
  const escaped = escapeHtml(String(props.modelValue ?? ''));

  if (!escaped) {
    return '';
  }

  return escaped.replace(/\{\{[\s\S]*?\}\}/g, (match) => {
    const tip = lookup.value.get(normalizeKey(match)) || '';
    const interactive = tip ? ' pointer-events-auto cursor-help' : '';
    const tipAttr = tip ? ` data-tip="${escapeHtml(tip)}"` : '';

    return `<span class="joinotify-var rounded bg-primary-50 text-primary-800${interactive}"${tipAttr}>${match}</span>`;
  });
});

function syncScroll() {
  if (mirrorRef.value && inputRef.value) {
    mirrorRef.value.scrollLeft = inputRef.value.scrollLeft;
  }
}

function syncSelection() {
  const input = inputRef.value;

  if (!input) {
    return;
  }

  selection.value = {
    start: input.selectionStart ?? String(props.modelValue || '').length,
    end: input.selectionEnd ?? String(props.modelValue || '').length,
  };
}

function handleInput(event: Event) {
  const value = (event.target as HTMLInputElement).value;
  emit('update:modelValue', value);
  emit('input', value);
  syncSelection();
  nextTick(syncScroll);
}

function handleChange(event: Event) {
  emit('change', (event.target as HTMLInputElement).value);
}

function insertVariable(placeholder: string) {
  if (props.disabled || !placeholder) {
    return;
  }

  const value = String(props.modelValue || '');
  const { start, end } = selection.value;
  const nextValue = `${value.slice(0, start)}${placeholder}${value.slice(end)}`;
  const cursor = start + placeholder.length;

  emit('update:modelValue', nextValue);
  emit('input', nextValue);

  void Promise.resolve().then(() => {
    const input = inputRef.value;

    if (input && !props.disabled) {
      input.focus();
      input.setSelectionRange(cursor, cursor);
      selection.value = { start: cursor, end: cursor };
      syncScroll();
    }
  });
}

// --- Tooltip -------------------------------------------------------------

const tipEl = ref<HTMLElement | null>(null);
const tipVisible = ref(false);
const tipText = ref('');
const tipStyle = ref<Record<string, string>>({});

let scrollHandler: (() => void) | null = null;

function hideTip() {
  tipVisible.value = false;
  tipText.value = '';

  if (scrollHandler) {
    window.removeEventListener('scroll', scrollHandler, true);
    scrollHandler = null;
  }
}

function positionTip(rect: DOMRect) {
  const margin = 8;
  let left = rect.left + rect.width / 2;
  const el = tipEl.value;

  if (el) {
    const half = el.offsetWidth / 2;
    left = Math.min(Math.max(left, half + margin), window.innerWidth - half - margin);
  }

  tipStyle.value = {
    top: `${rect.top - margin}px`,
    left: `${left}px`,
    transform: 'translate(-50%, -100%)',
  };
}

function handleMouseOver(event: MouseEvent) {
  const target = event.target as HTMLElement | null;
  const variable = target?.closest?.('.joinotify-var') as HTMLElement | null;

  if (!variable || !mirrorRef.value?.contains(variable)) {
    hideTip();
    return;
  }

  const text = variable.getAttribute('data-tip');

  if (!text) {
    hideTip();
    return;
  }

  const rect = variable.getBoundingClientRect();
  tipText.value = text;
  tipVisible.value = true;

  if (!scrollHandler) {
    scrollHandler = () => hideTip();
    window.addEventListener('scroll', scrollHandler, true);
  }

  nextTick(() => positionTip(rect));
}

onBeforeUnmount(() => {
  if (scrollHandler) {
    window.removeEventListener('scroll', scrollHandler, true);
    scrollHandler = null;
  }
});
</script>

<template>
  <label class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ label }}</span>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition focus-within:border-primary-700 focus-within:ring-4 focus-within:ring-primary-700/10">
      <div class="flex items-stretch">
        <div class="relative w-full">
          <input
            ref="inputRef"
            :id="id"
            :name="name"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            class="joinotify-input-group__control relative w-full border-0 bg-transparent px-4 py-3 text-sm leading-6 text-transparent caret-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-0 disabled:cursor-not-allowed disabled:bg-slate-50"
            @input="handleInput"
            @change="handleChange"
            @focus="syncSelection"
            @keyup="syncSelection"
            @mouseup="syncSelection"
            @select="syncSelection"
            @scroll="syncScroll"
          />

          <div
            ref="mirrorRef"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden whitespace-pre px-4 py-3 text-sm leading-6 text-slate-900"
            @mouseover="handleMouseOver"
            @mouseleave="hideTip"
            v-html="mirrorHtml"
          />
        </div>

        <VariablePicker
          v-if="Array.isArray(placeholders) && placeholders.length"
          :placeholders="placeholders"
          :disabled="disabled"
          button-class="inline-flex w-11 shrink-0 items-center justify-center border-0 border-l border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          @select="insertVariable"
        />
      </div>
    </div>

    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="tipVisible && tipText"
          ref="tipEl"
          class="pointer-events-none fixed z-[10050] w-max max-w-[240px] rounded-[0.375rem] bg-black/90 px-3 py-1.5 text-center text-[0.8125rem] leading-[1.4] text-white shadow-[0_0.5rem_1rem_rgba(0,0,0,0.15)]"
          :style="tipStyle"
          role="tooltip"
        >
          {{ tipText }}
        </div>
      </Transition>
    </Teleport>
  </label>
</template>
