<script setup>
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
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
  // Shows a top-right close (X) button. Enabled when the view was opened to
  // change an existing flow's trigger (from the canvas node menu), so the user
  // can dismiss the screen and return to the canvas.
  showClose: { type: Boolean, default: false },
});

defineEmits(['update:title', 'update:context', 'select-trigger', 'continue', 'back', 'update:continuing', 'close']);

const closeButtonStyle = {
  backgroundImage:
    'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z\'/%3e%3c/svg%3e")',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
  backgroundSize: '0.85rem auto',
};

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
      class="btn-close absolute right-5 top-5 z-20 box-content flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white p-2 text-slate-500 opacity-70 shadow-sm transition hover:opacity-100 focus:opacity-100 focus:outline-none"
      :style="closeButtonStyle"
      :aria-label="__('Close', textDomain)"
      @click="$emit('close')"
    >
      <span class="sr-only">{{ __('Close', textDomain) }}</span>
    </button>

    <div class="flex min-h-0 w-full flex-1">
      <aside class="hidden w-[360px] shrink-0 overflow-y-auto border-r border-slate-200 bg-slate-50/80 px-7 py-10 xl:block">
        <div class="max-w-[300px]">
          <template v-if="loading">
            <div class="h-8 w-4/5 animate-pulse rounded-full bg-slate-200" />
            <div class="mt-3 h-5 w-2/3 animate-pulse rounded-full bg-slate-200" />
          </template>

          <template v-else>
            <h2 class="text-[30px] font-semibold tracking-tight text-slate-900">{{ __('Choose trigger type', textDomain) }}</h2>

            <p class="mt-3 text-[15px] leading-7 text-slate-500">
              {{ __('The name is used only for internal workflow tracking.', textDomain) }}
            </p>
          </template>
        </div>

        <div class="mt-10 space-y-4">
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
              v-for="item in contexts"
              :key="item.id"
              type="button"
              class="flex w-full items-center gap-4 rounded-[12px] border px-4 py-4 text-left transition"
              :class="context === item.id
                ? 'border-blue-600 bg-blue-600 text-white shadow-[0_10px_22px_rgba(37,99,235,0.22)]'
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
          </template>
        </div>
      </aside>

      <div class="flex min-w-0 flex-1 flex-col">
        <div class="mx-auto flex w-full max-w-[1440px] min-h-0 flex-1 flex-col px-6 pt-12 sm:px-8 lg:px-12 xl:px-16">
          <div class="shrink-0">
            <div class="max-w-3xl">
              <template v-if="loading">
                <div class="h-9 w-3/5 animate-pulse rounded-full bg-slate-200" />
                <div class="mt-3 h-5 w-4/5 animate-pulse rounded-full bg-slate-200" />
              </template>
              <template v-else>
                <p class="text-[32px] font-semibold tracking-tight text-slate-900">
                  {{ __('Define a name for this flow', textDomain) }}
                </p>
                <p class="mt-3 text-[15px] leading-7 text-slate-500">
                  {{ __('The name is used only for internal workflow tracking.', textDomain) }}
                </p>
              </template>
            </div>

            <div class="mt-7 w-full">
              <div v-if="loading" class="h-[52px] rounded-[12px] border border-slate-200 bg-slate-100 animate-pulse" />
              <WorkflowNameField
                v-else
                :model-value="title"
                :label="__('Workflow name', textDomain)"
                :placeholder="__('My automation #129720', textDomain)"
                @update:model-value="$emit('update:title', $event)"
              />
            </div>
          </div>

          <div class="mt-10 min-h-0 w-full flex-1 overflow-y-auto pb-8">
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

        <div class="shrink-0 border-t border-slate-200 bg-white/75 backdrop-blur-md backdrop-saturate-150">
          <div class="mx-auto w-full max-w-[1440px] px-6 py-4 sm:px-8 lg:px-12 xl:px-16">
            <TriggerStepFooter :disabled="!ready" :continuing="continuing" @continue="$emit('continue')" @back="$emit('back')" />
          </div>
        </div>
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
