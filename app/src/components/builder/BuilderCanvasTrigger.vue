<script setup>
import { __, textDomain } from '../../utils/i18n';
import { ref } from 'vue';

defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  triggerId: { type: String, default: '' },
  active: { type: Boolean, default: false },
});

defineEmits(['click', 'edit']);
const menuOpen = ref(false);

function toggleMenu() {
  menuOpen.value = !menuOpen.value;
}

function closeMenu() {
  menuOpen.value = false;
}
</script>

<template>
  <div
    class="mx-auto flex w-full max-w-[1180px] items-stretch gap-4 rounded-[12px] border border-slate-100 bg-white px-6 py-5 shadow-[0_1px_4px_rgba(15,23,42,0.04),0_10px_24px_rgba(15,23,42,0.06)]"
    :class="active ? 'ring-1 ring-slate-200' : ''"
    :data-context="context"
    :data-trigger="trigger"
    :data-trigger-id="triggerId"
  >
    <button type="button" class="min-w-0 flex-1 text-left" @click="$emit('click')">
      <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
          <h4 class="text-lg font-semibold tracking-tight text-slate-900">
            {{ title || __('Trigger', textDomain) }}
          </h4>
          <p class="mt-2 max-w-[56rem] text-sm leading-6 text-slate-500">
            {{ description || context + ' / ' + trigger }}
          </p>
        </div>
      </div>
    </button>

    <div class="relative shrink-0 self-start" data-trigger-menu>
      <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
        :aria-label="__('Open trigger actions', textDomain)"
        @click="toggleMenu"
      >
        <span class="text-2xl leading-none">⋮</span>
      </button>

      <div
        v-if="menuOpen"
        class="absolute right-0 top-full z-20 mt-2 w-56 overflow-hidden rounded-[14px] border border-slate-200 bg-white p-2 shadow-[0_12px_30px_rgba(15,23,42,0.14)]"
      >
        <button
          type="button"
          class="flex w-full items-center gap-3 rounded-[10px] px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
          @click="$emit('edit'); closeMenu()"
        >
          <span class="text-base leading-none">✎</span>
          <span>{{ __('Settings', textDomain) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
