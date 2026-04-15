<script setup>
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';
import TriggerCard from './TriggerCard.vue';
import TriggerStepFooter from './TriggerStepFooter.vue';
import WorkflowNameField from './WorkflowNameField.vue';

defineProps({
  title: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  contexts: { type: Array, default: () => [] },
  triggers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  ready: { type: Boolean, default: false },
  continuing: { type: Boolean, default: false },
});

defineEmits(['update:title', 'update:context', 'select-trigger', 'continue', 'back', 'update:continuing']);

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

const triggerGridClass = computed(() => 'grid gap-4 sm:grid-cols-2 2xl:grid-cols-4 overflow-y-auto max-h-[450px]');

const skeletonCards = computed(() => Array.from({ length: 4 }, (_, index) => index));
const skeletonContexts = computed(() => Array.from({ length: 5 }, (_, index) => index));
</script>

<template>
  <section class="min-h-[calc(100vh-72px)] w-full bg-white">
    <div class="flex min-h-[calc(100vh-72px)] w-full">
      <aside class="hidden w-[360px] shrink-0 border-r border-slate-200 bg-slate-50/80 px-7 py-10 xl:block">
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

      <div class="min-w-0 flex-1 px-6 py-12 sm:px-8 lg:px-12 xl:px-16">
        <div class="mx-auto flex w-full max-w-[1440px] flex-col">
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

          <div class="mt-7 max-w-[1180px]">
            <div v-if="loading" class="h-[52px] rounded-[12px] border border-slate-200 bg-slate-100 animate-pulse" />
            <WorkflowNameField
              v-else
              :model-value="title"
              :label="__('Workflow name', textDomain)"
              :placeholder="__('My automation #129720', textDomain)"
              @update:model-value="$emit('update:title', $event)"
            />
          </div>

          <div class="mt-10 max-w-[1180px]">
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

          <div class="mt-auto max-w-[1180px] py-8">
            <TriggerStepFooter :disabled="!ready" :continuing="continuing" @continue="$emit('continue')" @back="$emit('back')" />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.builder-context-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
}
</style>
