<script setup lang="ts">
/**
 * StickyNote.vue
 *
 * Visual-only canvas annotation node (N8N-style sticky note). Renders markdown
 * for documentation, is editable in place, recolorable, draggable and
 * resizable. It carries no execution semantics and has no connection handles.
 *
 * @since 2.0.0
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Palette, Trash, X } from '@boxicons/vue';
import { useVueFlow } from '@vue-flow/core';
import { onClickOutside } from '@vueuse/core';
import { __, textDomain } from '../../utils/i18n';
import { renderMarkdown } from '../../utils/markdown';
import { hexToRgb, luminance, normalizeHex } from '../../utils/color';
import {
  MIN_NOTE_HEIGHT,
  MIN_NOTE_WIDTH,
  NOTE_COLORS,
  DEFAULT_NOTE_COLOR,
} from '../../utils/editorNotes';
import ColorPickerField from '../fields/ColorPickerField.vue';

interface StickyNoteData {
  content: string;
  color: string;
  width: number;
  height: number;
  onUpdate?: (id: string, patch: Record<string, unknown>) => void;
  onRemove?: (id: string) => void;
}

const props = defineProps<{
  id: string;
  data: StickyNoteData;
  selected?: boolean;
}>();

const { getViewport } = useVueFlow();

const color = computed(() => normalizeHex(props.data.color) || DEFAULT_NOTE_COLOR);

// Live dimensions mirror the persisted values but update smoothly while
// resizing; the committed value is emitted on pointer-up.
const localWidth = ref(props.data.width || MIN_NOTE_WIDTH);
const localHeight = ref(props.data.height || MIN_NOTE_HEIGHT);
let resizing = false;

watch(
  () => [props.data.width, props.data.height] as const,
  ([width, height]) => {
    if (resizing) {
      return;
    }
    localWidth.value = width || MIN_NOTE_WIDTH;
    localHeight.value = height || MIN_NOTE_HEIGHT;
  },
);

const rootStyle = computed(() => {
  const [r, g, b] = hexToRgb(color.value);

  return {
    width: `${localWidth.value}px`,
    height: `${localHeight.value}px`,
    backgroundColor: `rgba(${r}, ${g}, ${b}, 0.5)`,
    borderColor: `rgb(${r}, ${g}, ${b})`,
  };
});

// Pick a readable text color for the chosen background.
const textColor = computed(() => {
  const [r, g, b] = hexToRgb(color.value);
  return luminance(r, g, b) < 0.4 ? '#f8fafc' : '#1e293b';
});

const renderedContent = computed(() => renderMarkdown(props.data.content || ''));
const hasContent = computed(() => Boolean(String(props.data.content || '').trim()));

/* -------------------------------------------------------------------------- */
/* Inline editing                                                             */
/* -------------------------------------------------------------------------- */

const editing = ref(false);
const draft = ref(props.data.content || '');
const textareaRef = ref<HTMLTextAreaElement | null>(null);

watch(
  () => props.data.content,
  (value) => {
    if (!editing.value) {
      draft.value = value || '';
    }
  },
);

function emitUpdate(patch: Record<string, unknown>) {
  props.data.onUpdate?.(props.id, patch);
}

function startEditing() {
  editing.value = true;
  draft.value = props.data.content || '';
  void nextTick(() => {
    textareaRef.value?.focus();
    textareaRef.value?.select();
  });
}

function commitEditing() {
  if (!editing.value) {
    return;
  }

  editing.value = false;

  if (draft.value !== (props.data.content || '')) {
    emitUpdate({ content: draft.value });
  }
}

function onEditKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    editing.value = false;
    draft.value = props.data.content || '';
    return;
  }

  // Ctrl/Cmd+Enter saves and exits; a plain Enter keeps a normal newline.
  if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
    event.preventDefault();
    commitEditing();
  }
}

/* -------------------------------------------------------------------------- */
/* Color popover                                                              */
/* -------------------------------------------------------------------------- */

const colorPopoverRef = ref<HTMLElement | null>(null);
const colorOpen = ref(false);

onClickOutside(colorPopoverRef, () => {
  colorOpen.value = false;
});

function toggleColorPopover() {
  colorOpen.value = !colorOpen.value;
}

function chooseColor(value: string) {
  emitUpdate({ color: value });
}

const customColor = computed({
  get: () => color.value,
  set: (value: string) => {
    const normalized = normalizeHex(value);
    if (normalized) {
      emitUpdate({ color: normalized });
    }
  },
});

/* -------------------------------------------------------------------------- */
/* Resize                                                                     */
/* -------------------------------------------------------------------------- */

let startX = 0;
let startY = 0;
let startW = 0;
let startH = 0;

function onResizeMove(event: PointerEvent) {
  const zoom = getViewport()?.zoom || 1;
  localWidth.value = Math.max(MIN_NOTE_WIDTH, startW + (event.clientX - startX) / zoom);
  localHeight.value = Math.max(MIN_NOTE_HEIGHT, startH + (event.clientY - startY) / zoom);
}

function onResizeEnd() {
  if (!resizing) {
    return;
  }

  resizing = false;
  window.removeEventListener('pointermove', onResizeMove);
  window.removeEventListener('pointerup', onResizeEnd);
  emitUpdate({
    width: Math.round(localWidth.value),
    height: Math.round(localHeight.value),
  });
}

function onResizeStart(event: PointerEvent) {
  event.preventDefault();
  event.stopPropagation();
  resizing = true;
  startX = event.clientX;
  startY = event.clientY;
  startW = localWidth.value;
  startH = localHeight.value;
  window.addEventListener('pointermove', onResizeMove);
  window.addEventListener('pointerup', onResizeEnd);
}

function removeNote() {
  props.data.onRemove?.(props.id);
}

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', onResizeMove);
  window.removeEventListener('pointerup', onResizeEnd);
});
</script>

<template>
  <div
    class="joinotify-sticky-note"
    :class="{ 'is-selected': selected, 'is-editing': editing }"
    :style="rootStyle"
    @dblclick.stop="startEditing"
  >
    <!-- Floating toolbar (color + delete) -->
    <div class="joinotify-sticky-note__toolbar nodrag" @dblclick.stop>
      <div ref="colorPopoverRef" class="joinotify-sticky-note__color-wrap">
        <button
          type="button"
          class="joinotify-sticky-note__tool"
          :title="__('Change color', textDomain)"
          :aria-label="__('Change color', textDomain)"
          @click.stop="toggleColorPopover"
        >
          <Palette :width="15" :height="15" />
        </button>

        <div v-if="colorOpen" class="joinotify-sticky-note__popover nowheel" @dblclick.stop>
          <div class="joinotify-sticky-note__swatches">
            <button
              v-for="preset in NOTE_COLORS"
              :key="preset"
              type="button"
              class="joinotify-sticky-note__swatch"
              :class="{ 'is-active': preset === color }"
              :style="{ backgroundColor: preset }"
              :title="preset"
              :aria-label="preset"
              @click.stop="chooseColor(preset)"
            />
          </div>

          <div class="joinotify-sticky-note__custom">
            <span class="joinotify-sticky-note__custom-label">{{ __('Custom color', textDomain) }}</span>
            <ColorPickerField
              v-model="customColor"
              name="joinotify-sticky-note-color"
            />
          </div>
        </div>
      </div>

      <button
        type="button"
        class="joinotify-sticky-note__tool joinotify-sticky-note__tool--danger"
        :title="__('Delete note', textDomain)"
        :aria-label="__('Delete note', textDomain)"
        @click.stop="removeNote"
      >
        <Trash :width="15" :height="15" />
      </button>
    </div>

    <!-- Content -->
    <div class="joinotify-sticky-note__body nowheel" :style="{ color: textColor }">
      <textarea
        v-if="editing"
        ref="textareaRef"
        v-model="draft"
        class="joinotify-sticky-note__textarea nodrag"
        :placeholder="__('Write in markdown to document your flow…', textDomain)"
        spellcheck="false"
        @blur="commitEditing"
        @keydown="onEditKeydown"
        @pointerdown.stop
        @dblclick.stop
      />

      <div
        v-else-if="hasContent"
        class="joinotify-sticky-note__markdown"
        v-html="renderedContent"
      />

      <button
        v-else
        type="button"
        class="joinotify-sticky-note__placeholder nodrag"
        @click.stop="startEditing"
      >
        {{ __('Double-click to write a note in markdown', textDomain) }}
      </button>
    </div>

    <!-- Resize handle -->
    <div
      class="joinotify-sticky-note__resize nodrag"
      :title="__('Resize', textDomain)"
      @pointerdown="onResizeStart"
      @dblclick.stop
    >
      <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
        <path d="M11 1 1 11M11 5 5 11M11 9 9 11" stroke="currentColor" stroke-width="1.2" fill="none" />
      </svg>
    </div>
  </div>
</template>

<style scoped>
.joinotify-sticky-note {
  position: relative;
  display: flex;
  flex-direction: column;
  border-width: 1px;
  border-style: solid;
  border-radius: 12px;
  box-shadow: 0 6px 16px -8px rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(1px);
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
  /* Visible so the color popover can extend beyond the note; the body has its
     own scroll and the rounded background still clips to the border radius. */
  overflow: visible;
}

.joinotify-sticky-note.is-selected {
  box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.55), 0 8px 20px -8px rgba(15, 23, 42, 0.4);
}

.joinotify-sticky-note__toolbar {
  position: absolute;
  top: 6px;
  right: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
  opacity: 0;
  transform: translateY(-2px);
  transition: opacity 0.15s ease, transform 0.15s ease;
  z-index: 2;
}

.joinotify-sticky-note:hover .joinotify-sticky-note__toolbar,
.joinotify-sticky-note.is-selected .joinotify-sticky-note__toolbar {
  opacity: 1;
  transform: translateY(0);
}

.joinotify-sticky-note__color-wrap {
  position: relative;
}

.joinotify-sticky-note__tool {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 7px;
  border: 1px solid rgba(15, 23, 42, 0.12);
  background: rgba(255, 255, 255, 0.9);
  color: #334155;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.joinotify-sticky-note__tool:hover {
  background: #fff;
}

.joinotify-sticky-note__tool--danger:hover {
  background: #fee2e2;
  color: #dc2626;
}

.joinotify-sticky-note__popover {
  position: absolute;
  top: 32px;
  right: 0;
  width: 232px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
  box-shadow: 0 12px 30px -10px rgba(15, 23, 42, 0.35);
  z-index: 5;
}

.joinotify-sticky-note__swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.joinotify-sticky-note__swatch {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid rgba(15, 23, 42, 0.12);
  cursor: pointer;
  transition: transform 0.1s ease;
}

.joinotify-sticky-note__swatch:hover {
  transform: scale(1.08);
}

.joinotify-sticky-note__swatch.is-active {
  outline: 2px solid #4f46e5;
  outline-offset: 1px;
}

.joinotify-sticky-note__custom {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.joinotify-sticky-note__custom-label {
  display: block;
  margin-bottom: 6px;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
}

.joinotify-sticky-note__body {
  flex: 1 1 auto;
  min-height: 0;
  padding: 14px;
  overflow: auto;
  border-radius: 12px;
}

.joinotify-sticky-note__textarea {
  width: 100%;
  height: 100%;
  resize: none;
  border: none;
  outline: none;
  background: transparent;
  color: inherit;
  font-family: inherit;
  font-size: 13px;
  line-height: 1.5;
}

.joinotify-sticky-note__placeholder {
  width: 100%;
  height: 100%;
  min-height: 40px;
  border: none;
  background: transparent;
  color: inherit;
  opacity: 0.7;
  font-size: 13px;
  font-style: italic;
  text-align: left;
  cursor: text;
}

.joinotify-sticky-note__resize {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  padding: 2px;
  color: rgba(15, 23, 42, 0.35);
  cursor: nwse-resize;
  z-index: 2;
}

/* Markdown content styling (scoped, applies to v-html output). */
.joinotify-sticky-note__markdown {
  font-size: 13px;
  line-height: 1.55;
  word-break: break-word;
}

.joinotify-sticky-note__markdown :deep(h1) {
  font-size: 17px;
  font-weight: 700;
  margin: 0 0 6px;
}

.joinotify-sticky-note__markdown :deep(h2) {
  font-size: 15px;
  font-weight: 700;
  margin: 0 0 5px;
}

.joinotify-sticky-note__markdown :deep(h3) {
  font-size: 14px;
  font-weight: 600;
  margin: 0 0 4px;
}

.joinotify-sticky-note__markdown :deep(p) {
  margin: 0 0 8px;
}

.joinotify-sticky-note__markdown :deep(ul),
.joinotify-sticky-note__markdown :deep(ol) {
  margin: 0 0 8px;
  padding-left: 18px;
}

.joinotify-sticky-note__markdown :deep(li) {
  margin: 2px 0;
}

.joinotify-sticky-note__markdown :deep(a) {
  color: inherit;
  text-decoration: underline;
}

.joinotify-sticky-note__markdown :deep(code) {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 12px;
  padding: 1px 4px;
  border-radius: 4px;
  background: rgba(15, 23, 42, 0.1);
}

.joinotify-sticky-note__markdown :deep(pre) {
  margin: 0 0 8px;
  padding: 8px 10px;
  border-radius: 6px;
  background: rgba(15, 23, 42, 0.12);
  overflow-x: auto;
}

.joinotify-sticky-note__markdown :deep(pre code) {
  padding: 0;
  background: transparent;
}

.joinotify-sticky-note__markdown :deep(hr) {
  border: none;
  border-top: 1px solid rgba(15, 23, 42, 0.2);
  margin: 8px 0;
}
</style>
