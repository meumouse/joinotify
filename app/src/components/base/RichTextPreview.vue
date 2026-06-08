<script setup lang="ts">
/**
 * RichTextPreview.vue
 *
 * Renders the sanitized preview of a message and highlights every registered
 * text variable ({{ ... }}) in bold blue. Hovering a known variable reveals a
 * tooltip with its sandbox sample so the user understands what it resolves to.
 *
 * @since 2.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { escapeHtml, sanitizePreviewHtml } from '../../utils/html';
import { __, textDomain } from '../../utils/i18n';

interface PlaceholderItem {
  placeholder: string;
  description?: string;
  replacement?: Record<string, unknown>;
}

const props = defineProps({
  value: { type: String, default: '' },
  placeholders: { type: Array as () => Array<PlaceholderItem | string>, default: () => [] },
});

const containerRef = ref<HTMLElement | null>(null);
const tipEl = ref<HTMLElement | null>(null);
const tipVisible = ref(false);
const tipText = ref('');
const tipStyle = ref<Record<string, string>>({});

let scrollHandler: (() => void) | null = null;

/**
 * Normalize a placeholder token into a comparable key: strip the outer braces,
 * collapse inner whitespace and lowercase it so "{{ Post_Title }}" and
 * "{{post_title}}" resolve to the same entry.
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

const html = computed(() => {
  const sanitized = sanitizePreviewHtml(String(props.value || ''));

  return sanitized.replace(/\{\{[\s\S]*?\}\}/g, (match) => {
    const tip = lookup.value.get(normalizeKey(match)) || '';
    const known = lookup.value.has(normalizeKey(match));
    const tipAttr = tip ? ` data-tip="${escapeHtml(tip)}"` : '';
    const cursor = tip ? ' cursor-help' : '';

    return `<span class="joinotify-var rounded bg-primary-50 px-1 font-semibold text-primary-800${cursor}"${tipAttr} data-known="${known}">${escapeHtml(match)}</span>`;
  });
});

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

  if (!variable || !containerRef.value?.contains(variable)) {
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
  <div
    ref="containerRef"
    class="text-sm leading-6 text-slate-800"
    @mouseover="handleMouseOver"
    @mouseleave="hideTip"
    v-html="html"
  />

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
</template>
