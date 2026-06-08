<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BoldIcon from '@boxicons/vue/Bold';
import ItalicIcon from '@boxicons/vue/Italic';
import SmileIcon from '@boxicons/vue/Smile';
import UnderlineIcon from '@boxicons/vue/Underline';
import EmojiPicker from 'vue3-emoji-picker';
import 'vue3-emoji-picker/css';
import VariablePicker from './VariablePicker.vue';
import RichTextPreview from './RichTextPreview.vue';
import Tooltip from '../tooltips/Tooltip.vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  modelValue: { type: String, default: '' },
  id: { type: String, default: '' },
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  rows: { type: Number, default: 5 },
  placeholders: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'input', 'change']);

const rootRef = ref<HTMLElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const emojiButtonRef = ref<HTMLButtonElement | null>(null);
const emojiPopoverRef = ref<HTMLElement | null>(null);
const showEmojiPicker = ref(false);
const selection = ref({ start: 0, end: 0 });

const EMOJI_POPOVER_WIDTH = 352;
const popoverStyle = ref<Record<string, string>>({});

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
    syncSelection();
    nextTick(updatePopoverPosition);
  }
}

function syncSelection() {
  const textarea = textareaRef.value;

  if (!textarea) {
    return;
  }

  selection.value = {
    start: textarea.selectionStart ?? String(props.modelValue || '').length,
    end: textarea.selectionEnd ?? String(props.modelValue || '').length,
  };
}

function focusSelection(start: number, end: number) {
  nextTick(() => {
    const textarea = textareaRef.value;

    if (!textarea || props.disabled) {
      return;
    }

    textarea.focus();
    textarea.setSelectionRange(start, end);
  });
}

function emitValue(nextValue: string, start?: number, end?: number) {
  emit('update:modelValue', nextValue);
  emit('input', nextValue);

  if (typeof start === 'number' && typeof end === 'number') {
    focusSelection(start, end);
  }
}

function wrapSelection(before: string, after = before) {
  if (props.disabled) {
    return;
  }

  const value = String(props.modelValue || '');
  const { start, end } = selection.value;
  const hasSelection = end > start;
  const selectedText = value.slice(start, end);
  const insertedText = hasSelection ? `${before}${selectedText}${after}` : `${before}${after}`;
  const nextValue = `${value.slice(0, start)}${insertedText}${value.slice(end)}`;
  const cursorStart = start + before.length;
  const cursorEnd = hasSelection ? cursorStart + selectedText.length : cursorStart;

  showEmojiPicker.value = false;
  emitValue(nextValue, cursorStart, cursorEnd);
}

function insertEmoji(emoji: string) {
  if (props.disabled || !emoji) {
    return;
  }

  const value = String(props.modelValue || '');
  const { start, end } = selection.value;
  const nextValue = `${value.slice(0, start)}${emoji}${value.slice(end)}`;
  const cursor = start + emoji.length;

  showEmojiPicker.value = false;
  emitValue(nextValue, cursor, cursor);
}

function handleEmojiSelect(emoji: { i?: string } | string) {
  if (typeof emoji === 'string') {
    insertEmoji(emoji);
    return;
  }

  insertEmoji(String(emoji?.i || ''));
}

function insertVariable(placeholder: string) {
  if (props.disabled || !placeholder) {
    return;
  }

  const value = String(props.modelValue || '');
  const { start, end } = selection.value;
  const nextValue = `${value.slice(0, start)}${placeholder}${value.slice(end)}`;
  const cursor = start + placeholder.length;

  emitValue(nextValue, cursor, cursor);
}

function handleInput(event: Event) {
  emitValue((event.target as HTMLTextAreaElement).value);
}

function handleChange(event: Event) {
  emit('change', (event.target as HTMLTextAreaElement).value);
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

watch(
  () => props.modelValue,
  () => {
    syncSelection();
  },
);

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  window.addEventListener('resize', handleReposition);
  window.addEventListener('scroll', handleReposition, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  window.removeEventListener('resize', handleReposition);
  window.removeEventListener('scroll', handleReposition, true);
});
</script>

<template>
  <label ref="rootRef" class="flex flex-col gap-1.5">
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
              @click="wrapSelection('<strong>', '</strong>')"
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
              @click="wrapSelection('<em>', '</em>')"
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
              @click="wrapSelection('<u>', '</u>')"
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

        <textarea
          ref="textareaRef"
          :id="id"
          :name="name"
          :rows="rows"
          :value="modelValue"
          :placeholder="placeholder"
          :disabled="disabled"
          class="w-full resize-y border-0 bg-white px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition placeholder:text-slate-400 focus:ring-0 disabled:cursor-not-allowed disabled:bg-slate-50"
          @input="handleInput"
          @change="handleChange"
          @focus="syncSelection"
          @keyup="syncSelection"
          @mouseup="syncSelection"
          @select="syncSelection"
        />

        <div v-if="String(modelValue || '').trim()" class="border-t border-slate-200 bg-slate-50 px-4 py-3">
          <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">
            {{ __('Preview', textDomain) }}
          </p>
          <RichTextPreview :value="modelValue" :placeholders="placeholders" />
        </div>
      </div>

    </div>

    <p v-if="description" class="text-xs leading-5 text-slate-500">
      {{ description }}
    </p>
  </label>
</template>

<style>
.joinotify-emoji-popover .v3-emoji-picker {
  width: 100%;
  box-shadow: none;
  border: 0;
  border-radius: 0;
}
</style>
