<script setup>
import { computed, ref } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import BrandMark from '../brand/BrandMark.vue';
import TriggerCard from './TriggerCard.vue';
import TriggerStepFooter from './TriggerStepFooter.vue';
import WorkflowNameField from './WorkflowNameField.vue';

const props = defineProps({
  title: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  contexts: { type: Array, default: () => [] },
  triggers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  ready: { type: Boolean, default: false },
  continuing: { type: Boolean, default: false },
  // Shows a top-right close (X) button. It dismisses the screen and returns to
  // the place the trigger choice was opened from (the canvas when changing an
  // existing flow's trigger, or the start screen for a new, unsaved flow).
  showClose: { type: Boolean, default: false },
});

defineEmits(['update:title', 'update:context', 'select-trigger', 'continue', 'back', 'update:continuing', 'close']);

const search = ref('');

const filteredContexts = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) {
    return props.contexts;
  }

  return props.contexts.filter((item) => {
    const haystack = `${item.label || ''} ${item.id || ''}`.toLowerCase();

    return haystack.includes(term);
  });
});

function getContextFallbackLabel(item) {
  const source = String(item.label || item.id || '')
    .replace(/[^a-z0-9]+/gi, ' ')
    .trim();

  return source
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 3)
    .toUpperCase();
}

function getContextLogo(contextId) {
  return props.contexts.find((item) => item.id === contextId)?.icon_svg || '';
}

function getContextLabel(contextId) {
  return props.contexts.find((item) => item.id === contextId)?.label || contextId;
}

const triggerGridClass = computed(() => 'builder-trigger-grid grid gap-4');

const skeletonCards = computed(() => Array.from({ length: 4 }, (_, index) => index));
const skeletonContexts = computed(() => Array.from({ length: 5 }, (_, index) => index));
</script>

<template>
  <section class="relative flex h-full w-full flex-col overflow-hidden bg-white">
    <button
      v-if="showClose"
      type="button"
      class="absolute right-4 top-4 z-20 flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:text-slate-700 focus:text-slate-700 focus:outline-none"
      :aria-label="__('Close', textDomain)"
      @click="$emit('close')"
    >
      <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
        <path d="M1 1l14 14M15 1L1 15" />
      </svg>
      <span class="sr-only">{{ __('Close', textDomain) }}</span>
    </button>

    <div class="flex min-h-0 w-full flex-1 flex-col overflow-y-auto xl:flex-row xl:overflow-hidden">
      <aside class="w-full shrink-0 border-b border-slate-200 bg-slate-50/80 px-6 py-8 xl:w-[360px] xl:border-b-0 xl:border-r xl:overflow-y-auto xl:px-7 xl:py-10">
        <div class="max-w-[300px]">
          <BrandMark class="mb-6" :size="32" variant="primary" />

          <template v-if="loading">
            <div class="h-8 w-4/5 animate-pulse rounded-full bg-slate-200" />
            <div class="mt-3 h-5 w-2/3 animate-pulse rounded-full bg-slate-200" />
          </template>

          <template v-else>
            <h2 class="text-[28px] font-semibold tracking-tight text-slate-900">{{ __('Choose the source', textDomain) }}</h2>

            <p class="mt-3 text-[15px] leading-7 text-slate-500">
              {{ __('Where the trigger fires from. Select an integration to see its available triggers.', textDomain) }}
            </p>
          </template>
        </div>

        <div class="relative mt-8">
          <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
              <circle cx="9" cy="9" r="6" />
              <path d="M14 14l4 4" />
            </svg>
          </span>
          <input
            v-model="search"
            type="search"
            :placeholder="__('Search integration', textDomain)"
            :aria-label="__('Search integration', textDomain)"
            class="w-full rounded-[12px] border border-slate-200 bg-white py-3 pl-11 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:ring-4 focus:ring-primary-700/10"
          >
        </div>

        <div class="mt-5 space-y-3">
          <template v-if="loading">
            <div
              v-for="index in skeletonContexts"
              :key="`context-skeleton-${index}`"
              class="flex w-full items-center gap-4 rounded-[12px] border border-transparent bg-[#e8edf5] px-4 py-4"
            >
              <div class="h-9 w-9 animate-pulse rounded-full bg-white/70" />
              <div class="h-4 w-32 animate-pulse rounded-full bg-white/70" />
            </div>
          </template>
          <template v-else>
            <button
              v-for="item in filteredContexts"
              :key="item.id"
              type="button"
              class="flex w-full items-center gap-4 rounded-[12px] border px-4 py-4 text-left transition"
              :class="context === item.id
                ? 'border-blue-600 bg-blue-600 text-white'
                : 'border-transparent bg-[#e8edf5] text-slate-600 hover:border-slate-300 hover:bg-white'"
              @click="$emit('update:context', item.id)"
            >
              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border text-xs font-semibold tracking-[0.12em]"
                :class="context === item.id ? 'border-white/20 bg-white/15 text-white' : 'border-slate-200 bg-white text-slate-500 shadow-sm'"
                aria-hidden="true"
              >
                <span
                  v-if="item.icon_svg"
                  class="builder-context-icon bg-white flex h-full w-full items-center justify-center p-1.5"
                  v-html="item.icon_svg"
                />
                <span v-else>
                  {{ getContextFallbackLabel(item) }}
                </span>
              </span>
              <span class="min-w-0">
                <span class="block text-[14px] font-semibold uppercase tracking-[0.08em]">
                  {{ item.label }}
                </span>
              </span>
            </button>

            <p v-if="!filteredContexts.length" class="px-1 py-2 text-sm text-slate-400">
              {{ __('No integrations found.', textDomain) }}
            </p>
          </template>
        </div>
      </aside>

      <div class="flex min-w-0 flex-1 flex-col xl:overflow-hidden">
        <div class="mx-auto flex w-full max-w-[1440px] min-h-0 flex-1 flex-col px-6 pt-10 sm:px-8 lg:px-12 xl:px-16 xl:overflow-hidden">
          <div class="shrink-0">
            <div class="max-w-3xl pr-12">
              <template v-if="loading">
                <div class="h-9 w-3/5 animate-pulse rounded-full bg-slate-200" />
                <div class="mt-3 h-5 w-4/5 animate-pulse rounded-full bg-slate-200" />
              </template>
              <template v-else>
                <p class="text-[30px] font-semibold tracking-tight text-slate-900 sm:text-[32px]">
                  {{ __('Choose the trigger', textDomain) }}
                </p>
                <p class="mt-3 text-[15px] leading-7 text-slate-500">
                  {{ __('The trigger that starts this flow.', textDomain) }}
                </p>
              </template>
            </div>

            <div class="mt-7 w-full">
              <div v-if="loading" class="h-[52px] rounded-[12px] border border-slate-200 bg-slate-100 animate-pulse" />
              <div v-else class="flex flex-col gap-1.5">
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                  {{ __('Flow name', textDomain) }}
                  <span class="font-normal normal-case tracking-normal text-slate-400">· {{ __('internal use', textDomain) }}</span>
                </span>
                <WorkflowNameField
                  :model-value="title"
                  label=""
                  :placeholder="__('My automation #129720', textDomain)"
                  @update:model-value="$emit('update:title', $event)"
                />
              </div>
            </div>
          </div>

          <div class="mt-8 min-h-0 w-full flex-1 pb-8 xl:overflow-y-auto">
            <div v-if="loading" :class="triggerGridClass">
              <div
                v-for="index in skeletonCards"
                :key="index"
                class="flex min-h-[154px] animate-pulse flex-col rounded-2xl border border-slate-200 bg-white p-5"
              >
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-100" />
                <div class="mx-auto mt-4 h-5 w-24 rounded-full bg-slate-100" />
                <div class="mx-auto mt-4 h-4 w-full rounded-full bg-slate-100" />
                <div class="mx-auto mt-2 h-4 w-5/6 rounded-full bg-slate-100" />
              </div>
            </div>
            <div v-else-if="triggers.length" :class="triggerGridClass">
              <TriggerCard
                v-for="item in triggers"
                :key="item.id"
                :title="item.label"
                :description="item.description"
                :context-label="getContextLabel(item.contexts?.[0] || context)"
                :context-icon-svg="getContextLogo(item.contexts?.[0] || context)"
                :icon="item.icon"
                :icon-svg="item.iconSvg || item.icon_svg || ''"
                :selected="trigger === item.id"
                @click="$emit('select-trigger', item.id)"
              />
            </div>
            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center text-sm text-slate-500">
              {{ __('No triggers available for this integration.', textDomain) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="shrink-0 border-t border-slate-200 bg-white/75 backdrop-blur-md backdrop-saturate-150">
      <div class="mx-auto w-full max-w-[1440px] px-6 py-4 sm:px-8 lg:px-12 xl:px-16">
        <TriggerStepFooter :disabled="!ready" :continuing="continuing" @continue="$emit('continue')" @back="$emit('back')" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.builder-trigger-grid {
  grid-template-columns: 1fr;
  align-content: start;
}

@media (min-width: 640px) {
  .builder-trigger-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* Up to 1366px (and the mid-range) → 3 cards per row. */
@media (min-width: 1024px) and (max-width: 1600px) {
  .builder-trigger-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

/* Larger than 1600px → 4 cards per row. */
@media (min-width: 1601px) {
  .builder-trigger-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.builder-context-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
}
</style>
