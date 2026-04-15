<script setup lang="ts">
import { computed, ref, type PropType } from 'vue';
import ModalDialog from '../modals/ModalDialog.vue';
import { __, textDomain } from '../../utils/i18n';
import { getTriggerDefinition } from '../../registries/triggerRegistry';
import type { WorkflowFieldSchema } from '../../types/workflowBuilder';

const props = defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  context: { type: String, default: '' },
  trigger: { type: String, default: '' },
  contextIconSvg: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconSvg: { type: String, default: '' },
  settingsSchema: { type: Array as PropType<WorkflowFieldSchema[]>, default: () => [] },
  settingsComponent: { type: String, default: '' },
  requireSettings: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
});

defineEmits(['click', 'edit']);

const settingsOpen = ref(false);

const contextLabel = computed(() => {
  if (!props.context) {
    return __('Trigger', textDomain);
  }

  return props.context.replace(/[_-]+/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
});

const triggerDefinition = computed(() => {
  if (!props.context || !props.trigger) {
    return undefined;
  }

  return getTriggerDefinition(props.context, props.trigger);
});

const displayIconSvg = computed(() => {
  return String(triggerDefinition.value?.iconSvg || props.iconSvg || '').trim();
});

const displayIcon = computed(() => {
  return String(triggerDefinition.value?.icon || props.icon || '').trim();
});

const displayTitle = computed(() => {
  return String(triggerDefinition.value?.label || props.title || __('Trigger', textDomain));
});

const displayDescription = computed(() => {
  return String(triggerDefinition.value?.description || props.description || '');
});

const displayIconGlyph = computed(() => {
  const glyph = displayIcon.value.trim();
  return glyph ? glyph.slice(0, 1).toUpperCase() : 'T';
});

const hasSettings = computed(() => {
  return Boolean(props.requireSettings || props.settingsComponent || props.settingsSchema.length);
});

const settingsItems = computed(() => {
  return props.settingsSchema.map((field) => ({
    key: field.key,
    label: field.label || field.key,
    description: field.description || field.helper || '',
    component: field.component,
    required: Boolean(field.required),
  }));
});

function openSettings(event: MouseEvent) {
  event.stopPropagation();

  if (!hasSettings.value) {
    return;
  }

  settingsOpen.value = true;
}
</script>

<template>
  <div
    class="w-full rounded-xl border border-slate-200 bg-white p-5 text-left transition hover:border-slate-300 hover:shadow-[0_16px_44px_rgba(15,23,42,0.08)]"
    :class="active
      ? 'ring-2 ring-sky-200'
      : ''"
    role="button"
    tabindex="0"
    @click="$emit('click')"
    @keydown.enter.prevent="$emit('click')"
    @keydown.space.prevent="$emit('click')"
  >
  <div class="flex items-start gap-4">
    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-[14px] border border-slate-200 bg-slate-50 text-slate-500">
      <span
        v-if="contextIconSvg"
        class="trigger-context-icon flex h-full w-full items-center justify-center p-1.5"
        v-html="contextIconSvg"
      />
      <span
        v-else-if="displayIconSvg && displayIconSvg.startsWith('<svg')"
        class="flex h-5 w-5 items-center justify-center"
        v-html="displayIconSvg"
      />
        <span v-else-if="displayIcon" class="text-sm font-semibold uppercase tracking-[0.2em]">
          {{ displayIconGlyph }}
        </span>
        <span v-else class="text-sm font-semibold uppercase tracking-[0.2em]">
          T
        </span>
      </div>

      <div class="min-w-0 flex-1 pt-0.5">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
          {{ contextLabel }}
        </p>

        <h3 class="mt-1 text-lg font-semibold leading-6 text-slate-900">
          {{ displayTitle }}
        </h3>

        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ displayDescription }}
        </p>
      </div>

      <div v-if="hasSettings" class="shrink-0">
        <button
          type="button"
          class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          :aria-label="__('Open trigger settings', textDomain)"
          @click="openSettings"
        >
          <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
            <path d="M12 5.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 8.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 8.25a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
          </svg>
        </button>
      </div>
    </div>
  </div>

  <ModalDialog
    :open="settingsOpen"
    :title="__('Trigger settings', textDomain)"
    sizeClass="max-w-2xl"
    @close="settingsOpen = false"
  >
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
          {{ contextLabel }}
        </p>
        <h4 class="mt-1 text-lg font-semibold text-slate-900">
          {{ displayTitle }}
        </h4>
        <p class="mt-2 text-sm leading-6 text-slate-500">
          {{ displayDescription || __('No description available.', textDomain) }}
        </p>
      </div>

      <div v-if="settingsItems.length" class="space-y-3">
        <h5 class="text-sm font-semibold text-slate-900">
          {{ __('Available settings', textDomain) }}
        </h5>

        <div class="grid gap-3 sm:grid-cols-2">
          <div
            v-for="setting in settingsItems"
            :key="setting.key"
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-slate-900">
                  {{ setting.label }}
                </p>
                <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                  {{ setting.component }}
                </p>
              </div>

              <span
                v-if="setting.required"
                class="rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-rose-700"
              >
                {{ __('Required', textDomain) }}
              </span>
            </div>

            <p v-if="setting.description" class="mt-3 text-sm leading-6 text-slate-500">
              {{ setting.description }}
            </p>
          </div>
        </div>
      </div>

      <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5">
        <p class="text-sm font-medium text-slate-700">
          {{ __('No editable fields are exposed for this trigger.', textDomain) }}
        </p>
        <p v-if="settingsComponent || requireSettings" class="mt-2 text-sm leading-6 text-slate-500">
          {{ __('This trigger is configured through a custom backend component.', textDomain) }}
        </p>
      </div>
    </div>
  </ModalDialog>
</template>

<style scoped>
.trigger-context-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
  color: currentColor;
  fill: currentColor;
}
</style>
