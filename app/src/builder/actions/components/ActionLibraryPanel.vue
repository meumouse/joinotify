<script setup lang="ts">
import { computed, ref } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import ActionLibraryCard from './ActionLibraryCard.vue';
import type { ActionDefinition } from '../registry/types';
import { resolveSvgMarkup } from '../../../utils/icon';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  actions: { type: Array, default: () => [] },
  context: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  title: { type: String, default: 'Add an action' },
});

defineEmits(['select', 'close']);

const registry = useActionRegistry();
const query = ref('');

const closeButtonStyle = {
  backgroundImage:
    'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z\'/%3e%3c/svg%3e")',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
  backgroundSize: '0.75rem auto',
};

function normalizeAction(action: unknown): ActionDefinition | null {
  if (!action || typeof action !== 'object') {
    return null;
  }

  const source = action as Record<string, unknown>;
  const actionId = String(source.action || source.id || source.slug || '').trim();

  if (!actionId) {
    return null;
  }

  const contexts = Array.isArray(source.contexts)
    ? source.contexts.map((item) => String(item))
    : Array.isArray(source.context)
      ? source.context.map((item) => String(item))
      : typeof source.context === 'string'
        ? [source.context]
        : [];

  return {
    action: actionId,
    title: String(source.title || source.label || actionId),
    description: String(source.description || ''),
    icon: resolveSvgMarkup(source.iconSvg || source.icon_svg, source.icon) ? '' : String(source.icon || ''),
    iconSvg: resolveSvgMarkup(source.iconSvg || source.icon_svg, source.icon),
    context: contexts,
    hasSettings: Boolean(source.hasSettings ?? source.has_settings ?? false),
    priority: Number(source.priority || 0),
    isExpansible: Boolean(source.isExpansible ?? source.is_expansible),
    defaultData: (source.defaultData as Record<string, unknown>) || (source.default_data as Record<string, unknown>) || {},
    settingsSchema: (source.settingsSchema as ActionDefinition['settingsSchema']) || (source.settings_schema as ActionDefinition['settingsSchema']) || [],
    tags: Array.isArray(source.tags) ? source.tags.map((item) => String(item)) : [],
    enabled: source.enabled !== false,
  };
}

const availableActions = computed(() => {
  const source = Array.isArray(props.actions) && props.actions.length
    ? props.actions.map((action) => normalizeAction(action)).filter(Boolean)
    : (props.context ? registry.byContext(props.context) : registry.actions.value);
  const term = query.value.trim().toLowerCase();
  const context = String(props.context || '').trim();

  return (source as ActionDefinition[]).filter((action) => {
    const contexts = Array.isArray(action.context) ? action.context.map((item) => String(item).trim()).filter(Boolean) : [];
    const matchesContext = !context || contexts.length === 0 || contexts.includes(context);
    const searchable = [action.action, action.title, action.description, ...(action.tags || [])]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return matchesContext && (!term || searchable.includes(term));
  });
});
</script>

<template>
  <aside class="flex h-full w-[27rem] flex-col overflow-hidden border-l border-slate-200 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.12)]">
    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-6">
      <div>
        <h2 class="text-[1.4rem] font-semibold tracking-tight text-slate-900">
          {{ title }}
        </h2>
        <p class="mt-2 max-w-[20rem] text-sm leading-6 text-slate-500">
          Choose a step for the workflow. Actions are loaded from the registry and filtered by context.
        </p>
      </div>

      <button
          type="button"
          class="btn-close box-content flex h-4 w-4 shrink-0 items-center justify-center rounded border-0 bg-transparent p-[0.25rem] opacity-50 transition hover:opacity-75 focus:outline-none focus:opacity-100 disabled:pointer-events-none disabled:select-none disabled:opacity-25"
          :style="closeButtonStyle"
          :aria-label="__('Close panel', textDomain)"
          @click="$emit('close')"
        >
          <span class="sr-only">{{ __('Close', textDomain) }}</span>
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 max-h-[calc(100%-12rem)]">
      <label class="mb-4 block">
        <span class="sr-only">Search actions</span>
        <input
          v-model="query"
          type="search"
          placeholder="Search actions"
          class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:bg-white focus:ring-4 focus:ring-primary-700/10"
        />
      </label>

      <template v-if="loading">
        <div
          v-for="index in 5"
          :key="`action-skeleton-${index}`"
          class="joinotify-skeleton mb-3 flex items-start gap-3 rounded-[12px] border border-slate-200 bg-white p-4"
        >
          <div class="joinotify-skeleton mt-0.5 h-10 w-10 rounded-xl bg-slate-200/70" />
          <div class="min-w-0 flex-1">
            <div class="joinotify-skeleton h-4 w-32 rounded-full bg-slate-200/75" />
            <div class="joinotify-skeleton mt-3 h-3 w-40 rounded-full bg-slate-200/60" />
            <div class="joinotify-skeleton mt-2 h-3 w-28 rounded-full bg-slate-200/60" />
          </div>
        </div>
      </template>

      <template v-else>
        <ActionLibraryCard
          v-for="action in availableActions"
          :key="action.action"
          class="mb-3"
          :action="action"
          @click="$emit('select', $event)"
        />

        <div v-if="!availableActions.length" class="rounded-[14px] border border-dashed border-slate-300 px-4 py-8 text-center">
          <p class="text-sm font-medium text-slate-700">No actions available.</p>
          <p class="mt-1 text-sm leading-6 text-slate-500">Check the backend registry, the selected context, or the search term.</p>
        </div>
      </template>
    </div>
  </aside>
</template>
