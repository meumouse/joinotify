<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps({
  canMoveUp: { type: Boolean, default: true },
  canMoveDown: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: true },
  canDuplicate: { type: Boolean, default: true },
  canRemove: { type: Boolean, default: true },
  canAddBelow: { type: Boolean, default: true },
  canAddTrue: { type: Boolean, default: false },
  canAddFalse: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

defineEmits(['edit', 'duplicate', 'remove', 'move-up', 'move-down', 'add-below', 'add-true', 'add-false']);

const open = ref(false);

function toggle() {
  open.value = !open.value;
}

function close() {
  open.value = false;
}
</script>

<template>
  <div class="relative z-20">
    <button
      type="button"
      class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-[0_1px_6px_rgba(15,23,42,0.08)] transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
      :aria-label="'Open node actions'"
      @click.stop="toggle"
    >
      <span class="text-lg leading-none">...</span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-full mt-2 w-56 overflow-hidden rounded-[18px] border border-slate-200 bg-white p-2 shadow-[0_18px_45px_rgba(15,23,42,0.12)]"
      @click.stop
    >
      <button
        v-if="canEdit"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('edit'); close()"
      >
        <span>Edit</span>
      </button>

      <button
        v-if="canDuplicate"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('duplicate'); close()"
      >
        <span>Duplicate</span>
      </button>

      <button
        v-if="canMoveUp"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('move-up'); close()"
      >
        <span>Move up</span>
      </button>

      <button
        v-if="canMoveDown"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('move-down'); close()"
      >
        <span>Move down</span>
      </button>

      <button
        v-if="canAddBelow"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('add-below'); close()"
      >
        <span>Add below</span>
      </button>

      <button
        v-if="canAddTrue"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('add-true'); close()"
      >
        <span>Add true branch</span>
      </button>

      <button
        v-if="canAddFalse"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
        @click="$emit('add-false'); close()"
      >
        <span>Add false branch</span>
      </button>

      <button
        v-if="canRemove"
        type="button"
        class="flex w-full items-center gap-3 rounded-[12px] px-3 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50"
        @click="$emit('remove'); close()"
      >
        <span>Remove</span>
      </button>
    </div>
  </div>
</template>
