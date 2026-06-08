<script setup lang="ts">
/**
 * BaseRichTextArea.vue
 *
 * WYSIWYG rich text editor. Editing and preview share a single contenteditable
 * surface: bold/italic/underline render inline as you type and every registered
 * text variable ({{ ... }}) becomes a highlighted, non-editable chip with a
 * hover tooltip showing its sandbox sample.
 *
 * The public contract is unchanged: `modelValue` is the message source string
 * using <strong>/<em>/<u> tags, "\n" line breaks and {{ variable }} tokens, and
 * the same format is emitted back via update:modelValue / input / change.
 *
 * @since 2.0.0
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BoldIcon from '@boxicons/vue/Bold';
import ItalicIcon from '@boxicons/vue/Italic';
import SmileIcon from '@boxicons/vue/Smile';
import UnderlineIcon from '@boxicons/vue/Underline';
import EmojiPicker from 'vue3-emoji-picker';
import 'vue3-emoji-picker/css';
import VariablePicker from './VariablePicker.vue';
import Tooltip from '../tooltips/Tooltip.vue';
import { escapeHtml, sanitizePreviewHtml } from '../../utils/html';
import { __, textDomain } from '../../utils/i18n';

interface PlaceholderItem {
  placeholder: string;
  description?: string;
  replacement?: Record<string, unknown>;
}

const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  rows: { type: Number, default: 5 },
  placeholders: { type: Array as () => Array<PlaceholderItem | string>, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

const rootRef = ref<HTMLElement | null>(null);
const editorRef = ref<HTMLElement | null>(null);
const emojiButtonRef = ref<HTMLButtonElement | null>(null);
const emojiPopoverRef = ref<HTMLElement | null>(null);
const tipEl = ref<HTMLElement | null>(null);
const showEmojiPicker = ref(false);
const tipVisible = ref(false);
const tipText = ref('');
const tipStyle = ref<Record<string, string>>({});

const EMOJI_POPOVER_WIDTH = 352;
const popoverStyle = ref<Record<string, string>>({});

// Last value this editor itself produced. Used to tell apart our own edits
// (don't re-render — that would reset the caret) from external changes.
let internalValue = String(props.modelValue || '');
let savedRange: Range | null = null;
let scrollHandler: (() => void) | null = null;

const minHeight = computed(() => `${Math.max(props.rows, 1) * 1.5}rem`);
const isEmpty = computed(() => !String(props.modelValue || '').trim());

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

/**
 * Render a single {{ variable }} token as a non-editable chip carrying the raw
 * token (so it can be serialized back) and, when known, the tooltip sample.
 */
function chipHtml(raw: string): string {
  const key = normalizeKey(raw);
  const tip = lookup.value.get(key) || '';
  const cursor = tip ? ' cursor-help' : '';
  const tipAttr = tip ? ` data-tip="${escapeHtml(tip)}"` : '';

  return (
    `<span class="joinotify-var inline-block rounded bg-primary-50 px-1 font-semibold text-primary-800${cursor}"` +
    ` contenteditable="false" data-var="${escapeHtml(raw)}"${tipAttr}>${escapeHtml(raw)}</span>`
  );
}

/** Build the editor DOM markup from the stored source string. */
function buildEditorHtml(source: string): string {
  const safe = sanitizePreviewHtml(source);

  return safe.replace(/\{\{[\s\S]*?\}\}/g, (match) => chipHtml(match));
}

/** Serialize a DOM node back into the stored source format. */
function serializeNode(node: Node, isLastChild: boolean): string {
  if (node.nodeType === Node.TEXT_NODE) {
    return node.nodeValue || '';
  }

  if (node.nodeType !== Node.ELEMENT_NODE) {
    return '';
  }

  const el = node as HTMLElement;
  const variable = el.dataset?.var;

  if (variable) {
    return variable;
  }

  const tag = el.nodeName;

  if (tag === 'BR') {
    // The browser keeps a bogus <br> as the last child of a line — drop it so
    // it does not turn into a spurious trailing newline.
    return isLastChild ? '' : '\n';
  }

  let inner = serializeChildren(el);

  const style = el.style;

  if (style) {
    const weight = style.fontWeight;

    if (weight && (weight === 'bold' || parseInt(weight, 10) >= 600)) {
      inner = `<strong>${inner}</strong>`;
    }

    if (style.fontStyle === 'italic') {
      inner = `<em>${inner}</em>`;
    }

    if (/underline/.test(`${style.textDecoration} ${style.textDecorationLine}`)) {
      inner = `<u>${inner}</u>`;
    }
  }

  if (tag === 'STRONG' || tag === 'B') {
    return inner ? `<strong>${inner}</strong>` : '';
  }

  if (tag === 'EM' || tag === 'I') {
    return inner ? `<em>${inner}</em>` : '';
  }

  if (tag === 'U') {
    return inner ? `<u>${inner}</u>` : '';
  }

  // Block elements (from pasted content) become explicit line breaks.
  if (tag === 'DIV' || tag === 'P') {
    return `\n${inner}`;
  }

  return inner;
}

function serializeChildren(node: Node): string {
  const children = Array.from(node.childNodes);

  return children
    .map((child, index) => serializeNode(child, index === children.length - 1))
    .join('');
}

/** Read the current editor DOM as a source string. */
function serialize(): string {
  if (!editorRef.value) {
    return '';
  }

  return serializeChildren(editorRef.value).replace(/^\n/, '');
}

function render() {
  if (editorRef.value) {
    editorRef.value.innerHTML = buildEditorHtml(internalValue);
  }
}

function pushValue() {
  const next = serialize();

  if (next === internalValue) {
    return;
  }

  internalValue = next;
  emit('update:modelValue', next);
  emit('input', next);
}

function saveSelection() {
  const sel = window.getSelection();

  if (!sel || sel.rangeCount === 0) {
    return;
  }

  const range = sel.getRangeAt(0);

  if (editorRef.value?.contains(range.commonAncestorContainer)) {
    savedRange = range.cloneRange();
  }
}

/** Re-focus the editor and restore the last known caret/selection. */
function restoreSelection(): Range | null {
  const editor = editorRef.value;

  if (!editor) {
    return null;
  }

  editor.focus();

  const sel = window.getSelection();

  if (!sel) {
    return null;
  }

  if (savedRange && editor.contains(savedRange.commonAncestorContainer)) {
    sel.removeAllRanges();
    sel.addRange(savedRange);

    return savedRange;
  }

  // No saved caret — collapse to the end of the editor.
  const range = document.createRange();
  range.selectNodeContents(editor);
  range.collapse(false);
  sel.removeAllRanges();
  sel.addRange(range);

  return range;
}

/** Insert a DOM node at the current caret and place the caret right after it. */
function insertNodeAtCaret(node: Node) {
  const range = restoreSelection();

  if (!range) {
    return;
  }

  range.deleteContents();
  range.insertNode(node);
  range.setStartAfter(node);
  range.collapse(true);

  const sel = window.getSelection();
  sel?.removeAllRanges();
  sel?.addRange(range);

  saveSelection();
  pushValue();
}

function applyFormat(command: string) {
  if (props.disabled) {
    return;
  }

  restoreSelection();
  document.execCommand(command, false);
  saveSelection();
  pushValue();
}

function insertEmoji(emoji: string) {
  if (props.disabled || !emoji) {
    return;
  }

  showEmojiPicker.value = false;
  insertNodeAtCaret(document.createTextNode(emoji));
}

function handleEmojiSelect(emoji: { i?: string } | string) {
  insertEmoji(typeof emoji === 'string' ? emoji : String(emoji?.i || ''));
}

function insertVariable(placeholder: string) {
  if (props.disabled || !placeholder) {
    return;
  }

  const template = document.createElement('template');
  template.innerHTML = chipHtml(placeholder);
  const chip = template.content.firstChild;

  if (chip) {
    insertNodeAtCaret(chip);
  }
}

/**
 * Convert completed {{ ... }} tokens that were typed (or pasted) as plain text
 * into non-editable chips, in place, without disturbing the caret. This mirrors
 * what render() does for stored content, so a variable written by hand gets the
 * same highlight and hover tooltip as one inserted from the picker.
 */
function chipifyEditor() {
  const editor = editorRef.value;

  if (!editor) {
    return;
  }

  const sel = window.getSelection();
  let caretNode: Node | null = null;
  let caretOffset = 0;

  if (sel && sel.rangeCount > 0) {
    const range = sel.getRangeAt(0);
    caretNode = range.endContainer;
    caretOffset = range.endOffset;
  }

  // Collect plain-text nodes holding a complete token. Skip text already inside
  // a chip — its raw token would otherwise be matched and double-wrapped.
  const candidates: Text[] = [];
  const walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT);
  let current = walker.nextNode();

  while (current) {
    const parent = (current as Text).parentElement;

    if (!parent?.closest('.joinotify-var') && /\{\{[\s\S]*?\}\}/.test(current.nodeValue || '')) {
      candidates.push(current as Text);
    }

    current = walker.nextNode();
  }

  if (!candidates.length) {
    return;
  }

  let caretTarget: { node: Node; offset: number } | null = null;

  for (const textNode of candidates) {
    const text = textNode.nodeValue || '';
    const parent = textNode.parentNode;

    if (!parent) {
      continue;
    }

    const isCaretNode = textNode === caretNode;
    const fragment = document.createDocumentFragment();
    const regex = /\{\{[\s\S]*?\}\}/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = regex.exec(text)) !== null) {
      const start = match.index;
      const end = start + match[0].length;

      if (start > lastIndex) {
        const segment = document.createTextNode(text.slice(lastIndex, start));
        fragment.appendChild(segment);

        if (isCaretNode && caretOffset >= lastIndex && caretOffset <= start) {
          caretTarget = { node: segment, offset: caretOffset - lastIndex };
        }
      }

      const template = document.createElement('template');
      template.innerHTML = chipHtml(match[0]);
      const chip = template.content.firstChild;

      if (chip) {
        fragment.appendChild(chip);

        if (isCaretNode && caretOffset > start && caretOffset <= end) {
          caretTarget = { node: chip, offset: -1 };
        }
      }

      lastIndex = end;
    }

    if (lastIndex < text.length) {
      const tail = document.createTextNode(text.slice(lastIndex));
      fragment.appendChild(tail);

      if (isCaretNode && caretOffset >= lastIndex) {
        caretTarget = { node: tail, offset: caretOffset - lastIndex };
      }
    }

    parent.replaceChild(fragment, textNode);
  }

  // Restore the caret next to where the user was typing.
  if (sel) {
    const range = document.createRange();

    if (caretTarget && caretTarget.node.parentNode) {
      if (caretTarget.offset < 0) {
        range.setStartAfter(caretTarget.node);
      } else {
        const max = caretTarget.node.nodeValue?.length ?? 0;
        range.setStart(caretTarget.node, Math.min(caretTarget.offset, max));
      }
    } else {
      range.selectNodeContents(editor);
      range.collapse(false);
    }

    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  }
}

function handleInput() {
  chipifyEditor();
  saveSelection();
  pushValue();
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    document.execCommand('insertLineBreak');
    handleInput();
  }
}

function handlePaste(event: ClipboardEvent) {
  event.preventDefault();
  const text = event.clipboardData?.getData('text/plain') || '';
  document.execCommand('insertText', false, text);
  handleInput();
}

function handleBlur() {
  emit('change', internalValue);
}

function updatePopoverPosition() {
  const button = emojiButtonRef.value;

  if (!button) {
    return;
  }

  const rect = button.getBoundingClientRect();
  const margin = 8;
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  let left = rect.right - EMOJI_POPOVER_WIDTH;
  left = Math.min(Math.max(margin, left), viewportWidth - EMOJI_POPOVER_WIDTH - margin);
  left = Math.max(margin, left);

  const spaceBelow = viewportHeight - rect.bottom;
  const placeAbove = spaceBelow < 360 && rect.top > spaceBelow;

  const style: Record<string, string> = {
    position: 'fixed',
    left: `${Math.round(left)}px`,
    width: `${EMOJI_POPOVER_WIDTH}px`,
    zIndex: '10000',
  };

  if (placeAbove) {
    style.bottom = `${Math.round(viewportHeight - rect.top + margin)}px`;
  } else {
    style.top = `${Math.round(rect.bottom + margin)}px`;
  }

  popoverStyle.value = style;
}

function toggleEmojiPicker() {
  if (props.disabled) {
    return;
  }

  showEmojiPicker.value = !showEmojiPicker.value;

  if (showEmojiPicker.value) {
    saveSelection();
    nextTick(updatePopoverPosition);
  }
}

function handleDocumentClick(event: MouseEvent) {
  if (!showEmojiPicker.value) {
    return;
  }

  const target = event.target as Node | null;

  if (target && (rootRef.value?.contains(target) || emojiPopoverRef.value?.contains(target))) {
    return;
  }

  showEmojiPicker.value = false;
}

function handleReposition() {
  if (showEmojiPicker.value) {
    updatePopoverPosition();
  }
}

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

  if (!variable || !editorRef.value?.contains(variable)) {
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

watch(
  () => props.modelValue,
  (value) => {
    const next = String(value || '');

    if (next === internalValue) {
      return;
    }

    internalValue = next;
    render();
  },
);

// Re-render chips when the placeholder set changes (tooltips / known styling).
watch(
  () => props.placeholders,
  () => render(),
  { deep: true },
);

onMounted(() => {
  render();
  document.addEventListener('click', handleDocumentClick);
  window.addEventListener('resize', handleReposition);
  window.addEventListener('scroll', handleReposition, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  window.removeEventListener('resize', handleReposition);
  window.removeEventListener('scroll', handleReposition, true);

  if (scrollHandler) {
    window.removeEventListener('scroll', scrollHandler, true);
    scrollHandler = null;
  }
});
</script>

<template>
  <div ref="rootRef" class="flex flex-col gap-1.5">
    <span v-if="label" class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
      {{ label }}
    </span>

    <div class="relative">
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
          <Tooltip :content="__('Bold', textDomain)">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="disabled"
              :aria-label="__('Bold', textDomain)"
              @mousedown.prevent
              @click="applyFormat('bold')"
            >
              <BoldIcon width="14" height="14" />
            </button>
          </Tooltip>

          <Tooltip :content="__('Italic', textDomain)">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="disabled"
              :aria-label="__('Italic', textDomain)"
              @mousedown.prevent
              @click="applyFormat('italic')"
            >
              <ItalicIcon width="14" height="14" />
            </button>
          </Tooltip>

          <Tooltip :content="__('Underline', textDomain)">
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="disabled"
              :aria-label="__('Underline', textDomain)"
              @mousedown.prevent
              @click="applyFormat('underline')"
            >
              <UnderlineIcon width="14" height="14" />
            </button>
          </Tooltip>

          <div class="mx-1 h-4 w-px bg-slate-200" />

          <div class="relative">
            <Tooltip :content="__('Emojis', textDomain)">
              <button
                ref="emojiButtonRef"
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                :class="{ 'bg-slate-200 text-slate-800': showEmojiPicker }"
                :disabled="disabled"
                :aria-label="__('Emojis', textDomain)"
                @mousedown.prevent
                @click="toggleEmojiPicker"
              >
                <SmileIcon width="14" height="14" />
              </button>
            </Tooltip>

            <Teleport to="body">
              <div
                v-if="showEmojiPicker"
                ref="emojiPopoverRef"
                class="joinotify-emoji-popover overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                :style="popoverStyle"
                @mousedown.prevent
              >
                <EmojiPicker
                  :native="true"
                  :hide-search="true"
                  :display-recent="true"
                  theme="light"
                  @select="handleEmojiSelect"
                />
              </div>
            </Teleport>
          </div>

          <VariablePicker
            v-if="Array.isArray(placeholders) && placeholders.length"
            :placeholders="placeholders"
            :disabled="disabled"
            @select="insertVariable"
          />
        </div>

        <div
          ref="editorRef"
          :id="id"
          class="rte-editor w-full resize-y overflow-auto whitespace-pre-wrap break-words px-4 py-3 text-sm leading-6 text-slate-900 outline-none focus:ring-0"
          :class="{ 'is-empty': isEmpty, 'cursor-not-allowed bg-slate-50': disabled }"
          :contenteditable="!disabled"
          :data-placeholder="placeholder"
          :style="{ minHeight }"
          role="textbox"
          aria-multiline="true"
          @input="handleInput"
          @keydown="handleKeydown"
          @paste="handlePaste"
          @blur="handleBlur"
          @keyup="saveSelection"
          @mouseup="saveSelection"
          @mouseover="handleMouseOver"
          @mouseleave="hideTip"
        />
      </div>
    </div>

    <p v-if="description" class="text-xs leading-5 text-slate-500">
      {{ description }}
    </p>

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
  </div>
</template>

<style>
.joinotify-emoji-popover .v3-emoji-picker {
  width: 100%;
  box-shadow: none;
  border: 0;
  border-radius: 0;
}
</style>

<style scoped>
.rte-editor.is-empty::before {
  content: attr(data-placeholder);
  color: #94a3b8;
  pointer-events: none;
}
</style>
