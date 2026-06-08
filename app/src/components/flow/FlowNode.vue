<script setup lang="ts">
/**
 * FlowNode.vue
 *
 * Custom node used in the vue-flow canvas.
 *
 * @since 1.4.7
 */
import { computed, ref } from 'vue';
import { Cog, DotsVerticalRounded, Repeat, Trash } from '@boxicons/vue';
import { Handle, Position } from '@vue-flow/core';
import { onClickOutside } from '@vueuse/core';
import { getFlowNodeConfig } from './flowNodeTypes';
import { resolveSvgMarkup } from '../../utils/icon';
import { __, textDomain } from '../../utils/i18n';

export interface FlowNodeData {
  type: string;
  actionId?: string;
  label: string;
  description?: string;
  config?: Record<string, unknown>;
  icon?: string;
  iconSvg?: string;
  contextLabel?: string;
  contextIconSvg?: string;
  contextIcon?: string;
  contextIconUrl?: string;
  hasSettings?: boolean;
  needsSetup?: boolean;
  onRequestDelete?: (id: string) => void;
  onEdit?: (id: string, data: { label: string; description: string; config?: Record<string, unknown> }) => void;
  onSelect?: (id: string) => void;
  onChangeTrigger?: (id: string) => void;
  onAddAction?: (id: string, branchKey?: 'action_true' | 'action_false') => void;
}

const props = defineProps<{
  id: string;
  data: FlowNodeData;
  selected?: boolean;
}>();

const fallbackConfig = computed(() => {
  return getFlowNodeConfig(String(props.data.actionId || '')) || getFlowNodeConfig(props.data.type);
});
const isCondition = computed(() => props.data.type === 'condition');
const isTrigger = computed(() => props.data.type === 'trigger');
const isStopAutomation = computed(() => String(props.data.actionId || props.data.type || '').trim() === 'stop_funnel');
const needsSetup = computed(() => Boolean(props.data.needsSetup));
// Every node has a menu: triggers expose "Change trigger" (+ Settings when the
// trigger has settings); action nodes expose Settings + Delete.
const showMenu = computed(() => true);
const showSettings = computed(() => !isTrigger.value || Boolean(props.data.hasSettings));
const menuRef = ref<HTMLElement | null>(null);
const menuOpen = ref(false);

const resolvedIconSvg = computed(() => resolveSvgMarkup(props.data.iconSvg, props.data.icon));
const contextIconSvg = computed(() => String(props.data.contextIconSvg || '').trim());
const contextIcon = computed(() => String(props.data.contextIcon || '').trim());
const contextIconUrl = computed(() => String(props.data.contextIconUrl || '').trim());
const displayIcon = computed(() => String(props.data.icon || fallbackConfig.value?.icon || '').trim());
const resolvedBoxiconClass = computed(() => normalizeBoxiconClass(displayIcon.value));
const displayColorClass = computed(() => fallbackConfig.value?.color || 'bg-slate-500');

// Integration/group label shown below the title for actions tied to an integration.
const ACTION_GROUP_LABELS: Record<string, string> = {
  send_whatsapp_message_text: 'WhatsApp',
  send_whatsapp_message_media: 'WhatsApp',
  create_coupon: 'WooCommerce',
};
const actionGroupLabel = computed(() => {
  if (isTrigger.value) {
    return '';
  }

  const key = String(props.data.actionId || props.data.type || '').trim();
  return String(props.data.contextLabel || ACTION_GROUP_LABELS[key] || '').trim();
});

onClickOutside(menuRef, () => {
  menuOpen.value = false;
});

function openSettings() {
  selectNode();
  menuOpen.value = false;
}

function changeTrigger() {
  props.data.onChangeTrigger?.(props.id);
  menuOpen.value = false;
}

function requestDelete() {
  props.data.onRequestDelete?.(props.id);
  menuOpen.value = false;
}

function selectNode() {
  props.data.onSelect?.(props.id);
}

function requestAddAction(branchKey?: 'action_true' | 'action_false') {
  props.data.onAddAction?.(props.id, branchKey);
}

function iconGlyph(value: string) {
  const normalized = String(value || '').trim();
  return normalized ? normalized.slice(0, 1).toUpperCase() : 'A';
}

function isBoxiconClass(value: string) {
  return /^bx[lrs]?-/.test(String(value || '').trim());
}

function normalizeBoxiconClass(value: string) {
  const normalized = String(value || '').trim().toLowerCase();

  if (!normalized) {
    return '';
  }

  if (isBoxiconClass(normalized)) {
    return normalized;
  }

  const split = normalized.split(/\s+/).filter(Boolean);
  const classToken = split.find((token) => isBoxiconClass(token));

  if (classToken) {
    return classToken;
  }

  if (/^[a-z0-9-]+$/.test(normalized)) {
    return `bx-${normalized}`;
  }

  return '';
}
</script>

<template>
  <div
    class="group relative w-[300px] rounded-xl border bg-white shadow-md transition-all duration-200 select-none"
    :class="[
      selected
        ? 'border-primary-600 ring-2 ring-primary-600/20'
        : needsSetup
          ? 'border-amber-300 ring-2 ring-amber-300/30 hover:border-amber-400'
          : 'border-slate-200 hover:border-slate-300 hover:shadow-lg',
    ]"
    @click="selectNode"
  >
    <Handle
      v-if="!isTrigger"
      id="input"
      type="target"
      :position="Position.Top"
      :style="{ top: '-7px', width: '12px', height: '12px', background: '#94a3b8', border: '2px solid white' }"
    />

    <div class="flex items-center gap-2.5 border-b border-slate-100 px-3 py-2.5">
      <div
        class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-md text-white"
        :class="displayColorClass"
      >
        <span
          v-if="isTrigger && contextIconSvg"
          class="flow-node-context-icon flex h-full w-full items-center justify-center bg-white p-1"
          v-html="contextIconSvg"
        />
        <img
          v-else-if="isTrigger && contextIconUrl"
          :src="contextIconUrl"
          :alt="data.contextLabel || data.label"
          class="flow-node-context-logo h-full w-full object-contain bg-white p-1"
          loading="lazy"
        />
        <span
          v-else-if="isTrigger && contextIcon"
          class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700"
        >
          {{ iconGlyph(contextIcon) }}
        </span>
        <span
          v-else-if="resolvedIconSvg"
          class="flow-node-action-icon flex h-4 w-4 items-center justify-center"
          v-html="resolvedIconSvg"
        />
        <i v-else-if="resolvedBoxiconClass" :class="`bx ${resolvedBoxiconClass} text-white`" style="font-size: 14px;" />
        <span v-else class="text-[11px] font-semibold uppercase tracking-[0.18em]">
          {{ iconGlyph(displayIcon || data.label) }}
        </span>
      </div>

      <div class="min-w-0 flex-1">
        <p
          v-if="isTrigger && data.contextLabel"
          class="truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400"
        >
          {{ data.contextLabel }}
        </p>
        <span class="block truncate text-sm font-semibold text-slate-900">
          {{ data.label }}
        </span>
        <p
          v-if="!isTrigger && actionGroupLabel"
          class="truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400"
        >
          {{ actionGroupLabel }}
        </p>
      </div>

      <div v-if="showMenu" ref="menuRef" class="relative">
        <button
          type="button"
          class="relative rounded p-0.5 text-slate-400 transition-opacity hover:text-slate-700"
          :class="[
            needsSetup
              ? 'text-amber-500 opacity-100 hover:text-amber-600'
              : 'opacity-0 group-hover:opacity-100',
          ]"
          :aria-label="__('Options', textDomain)"
          @click.stop="menuOpen = !menuOpen"
        >
          <DotsVerticalRounded :size="16" />
          <span
            v-if="needsSetup"
            class="absolute -right-0.5 -top-0.5 flex h-2.5 w-2.5"
            aria-hidden="true"
          >
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75" />
            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500" />
          </span>
        </button>

        <div
          v-if="menuOpen"
          class="absolute right-0 top-full z-50 mt-1 w-52 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-xl"
          @click.stop
        >
          <button
            v-if="isTrigger"
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
            @click="changeTrigger"
          >
            <Repeat :width="13" :height="13" />
            {{ __('Change trigger', textDomain) }}
          </button>
          <button
            v-if="showSettings"
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
            @click="openSettings"
          >
            <Cog :width="13" :height="13" />
            {{ __('Settings', textDomain) }}
          </button>
          <button
            v-if="!isTrigger"
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 transition-colors hover:bg-red-50"
            @click="requestDelete"
          >
            <Trash :width="13" :height="13" />
            {{ __('Delete', textDomain) }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="data.description" class="px-3 py-2.5">
      <!-- Descriptions are plugin-generated HTML (bold spans, placeholder pills, <br>). -->
      <div
        class="builder-node-description line-clamp-3 whitespace-pre-line text-xs leading-relaxed text-slate-400"
        v-html="data.description"
      />
    </div>

    <template v-if="isCondition">
      <div class="flex items-end justify-between px-5 pb-3 pt-1">
        <div class="flex flex-col items-center gap-1">
          <span class="text-[10px] font-semibold text-emerald-600">{{ __('True', textDomain) }}</span>
          <Handle
            id="true"
            type="source"
            :position="Position.Bottom"
            :style="{
              position: 'relative',
              transform: 'none',
              inset: 'auto',
              width: '12px',
              height: '12px',
              background: '#10b981',
              border: '2px solid #6ee7b7',
              cursor: 'pointer',
            }"
          >
            <span
              class="nodrag flow-node-add-hit"
              :title="__('Add action (True)', textDomain)"
              :aria-label="__('Add action to the true branch', textDomain)"
              @click.stop="requestAddAction('action_true')"
            />
          </Handle>
        </div>
        <div class="flex flex-col items-center gap-1">
          <span class="text-[10px] font-semibold text-red-500">{{ __('False', textDomain) }}</span>
          <Handle
            id="false"
            type="source"
            :position="Position.Bottom"
            :style="{
              position: 'relative',
              transform: 'none',
              inset: 'auto',
              width: '12px',
              height: '12px',
              background: '#ef4444',
              border: '2px solid #fca5a5',
              cursor: 'pointer',
            }"
          >
            <span
              class="nodrag flow-node-add-hit"
              :title="__('Add action (False)', textDomain)"
              :aria-label="__('Add action to the false branch', textDomain)"
              @click.stop="requestAddAction('action_false')"
            />
          </Handle>
        </div>
      </div>
    </template>

    <template v-else-if="!isStopAutomation">
      <Handle
        id="output"
        type="source"
        :position="Position.Bottom"
        :style="{ bottom: '-7px', width: '12px', height: '12px', background: '#94a3b8', border: '2px solid white', cursor: 'pointer' }"
      >
        <span
          class="nodrag flow-node-add-hit"
          :title="__('Add action', textDomain)"
          :aria-label="__('Add action', textDomain)"
          @click.stop="requestAddAction()"
        />
      </Handle>
    </template>
  </div>
</template>

<style scoped>
/* Clickable hit-area filling the output handle so clicking the dot opens the
   action sidebar. Slightly larger than the dot for an easier target, while the
   handle itself still starts a drag-connection. */
.flow-node-add-hit {
  position: absolute;
  inset: -5px;
  border-radius: 9999px;
  cursor: pointer;
}

.flow-node-context-icon :deep(svg),
.flow-node-action-icon :deep(svg) {
  display: block;
  width: 100%;
  height: 100%;
  color: currentColor;
  fill: currentColor;
}

.flow-node-context-icon {
  color: #334155;
}

/* Render the plugin-generated HTML description (bold spans + placeholder pills). */
.builder-node-description :deep(.builder-placeholder) {
  display: inline-block;
  padding: 0 5px;
  border-radius: 5px;
  background: #eef2ff;
  color: #4338ca;
  font-weight: 600;
  line-height: 1.5;
  white-space: nowrap;
}

.builder-node-description :deep(strong),
.builder-node-description :deep(b) {
  font-weight: 700;
  color: #334155;
}

.builder-node-description :deep(a) {
  color: #4338ca;
  text-decoration: underline;
}
</style>
