<script setup lang="ts">
/**
 * ActionLibraryModal.vue
 *
 * Large, centered "marketplace" modal that lists every action available for the
 * current trigger context, grouped into category tabs (Messages, WooCommerce,
 * ...). Categories — including ones registered by third parties through the
 * `Joinotify/Builder/Action_Categories` PHP filter — are received from the
 * backend catalog and rendered as tabs at the top of the modal.
 *
 * @since 1.4.7
 */
import { computed, ref, watch } from 'vue';
import { useActionRegistry } from '../composables/useActionRegistry';
import ActionLibraryCard from './ActionLibraryCard.vue';
import type { ActionCategory, ActionDefinition } from '../registry/types';
import { resolveSvgMarkup } from '../../../utils/icon';
import { __, textDomain } from '../../../utils/i18n';

const props = defineProps({
  open: { type: Boolean, default: false },
  actions: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  context: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  title: { type: String, default: () => __('Add an action', textDomain) },
});

const emit = defineEmits(['select', 'close']);

const registry = useActionRegistry();
const query = ref('');
const activeTab = ref('all');

const ALL_TAB = 'all';

const closeButtonStyle = {
  backgroundImage:
    'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z\'/%3e%3c/svg%3e")',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
  backgroundSize: '0.75rem auto',
};

// Reset search and active tab every time the modal opens.
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      query.value = '';
      activeTab.value = ALL_TAB;
    }
  },
);

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
    category: String(source.category || '').trim() || 'general',
    hasSettings: Boolean(source.hasSettings ?? source.has_settings ?? false),
    priority: Number(source.priority || 0),
    isExpansible: Boolean(source.isExpansible ?? source.is_expansible),
    defaultData: (source.defaultData as Record<string, unknown>) || (source.default_data as Record<string, unknown>) || {},
    settingsSchema: (source.settingsSchema as ActionDefinition['settingsSchema']) || (source.settings_schema as ActionDefinition['settingsSchema']) || [],
    tags: Array.isArray(source.tags) ? source.tags.map((item) => String(item)) : [],
    enabled: source.enabled !== false,
  };
}

/** Actions available for the current trigger context (before search/tab filtering). */
const contextActions = computed<ActionDefinition[]>(() => {
  registry.revision.value;

  const source = Array.isArray(props.actions) && props.actions.length
    ? (props.actions.map((action) => normalizeAction(action)).filter(Boolean) as ActionDefinition[])
    : props.context
      ? registry.byContext(props.context)
      : registry.actions.value;

  const context = String(props.context || '').trim();

  return source.filter((action) => {
    const contexts = Array.isArray(action.context)
      ? action.context.map((item) => String(item).trim()).filter(Boolean)
      : [];

    return !context || contexts.length === 0 || contexts.includes(context);
  });
});

/** Context-available actions narrowed down by the search term. */
const searchedActions = computed<ActionDefinition[]>(() => {
  const term = query.value.trim().toLowerCase();

  if (!term) {
    return contextActions.value;
  }

  return contextActions.value.filter((action) => {
    const searchable = [action.action, action.title, action.description, ...(action.tags || [])]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return searchable.includes(term);
  });
});

function categoryOf(action: ActionDefinition): string {
  return String(action.category || '').trim() || 'general';
}

function humanizeCategory(id: string): string {
  if (!id) {
    return __('Other', textDomain);
  }

  return id
    .replace(/[-_]+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

/** Map of backend-provided category metadata, keyed by id. */
const categoryMeta = computed<Record<string, ActionCategory>>(() => {
  const map: Record<string, ActionCategory> = {};

  (props.categories as ActionCategory[]).forEach((category) => {
    const id = String(category?.id || '').trim();

    if (id) {
      map[id] = category;
    }
  });

  return map;
});

/** Number of search-filtered actions per category id. */
const countsByCategory = computed<Record<string, number>>(() => {
  const counts: Record<string, number> = {};

  searchedActions.value.forEach((action) => {
    const id = categoryOf(action);
    counts[id] = (counts[id] || 0) + 1;
  });

  return counts;
});

interface CategoryTab {
  id: string;
  label: string;
  iconSvg: string;
  count: number;
}

/**
 * Tabs shown at the top of the modal: an "All" tab followed by every category
 * that currently has at least one matching action. Backend category order
 * (priority) is preserved; categories present only on the actions (e.g. a
 * third-party action with an unregistered category) are appended afterwards.
 */
const tabs = computed<CategoryTab[]>(() => {
  const counts = countsByCategory.value;
  const result: CategoryTab[] = [
    {
      id: ALL_TAB,
      label: __('All', textDomain),
      iconSvg: '',
      count: searchedActions.value.length,
    },
  ];

  const seen = new Set<string>();

  (props.categories as ActionCategory[]).forEach((category) => {
    const id = String(category?.id || '').trim();

    if (!id || seen.has(id) || !counts[id]) {
      return;
    }

    seen.add(id);
    result.push({
      id,
      label: String(category.label || humanizeCategory(id)),
      iconSvg: resolveSvgMarkup(undefined, category.icon),
      count: counts[id],
    });
  });

  Object.keys(counts)
    .filter((id) => !seen.has(id))
    .sort((left, right) => left.localeCompare(right))
    .forEach((id) => {
      const meta = categoryMeta.value[id];

      seen.add(id);
      result.push({
        id,
        label: String(meta?.label || humanizeCategory(id)),
        iconSvg: resolveSvgMarkup(undefined, meta?.icon),
        count: counts[id],
      });
    });

  return result;
});

// If the active tab disappears (e.g. filtered out by search), fall back to "All".
watch(tabs, (next) => {
  if (!next.some((tab) => tab.id === activeTab.value)) {
    activeTab.value = ALL_TAB;
  }
});

/** Actions rendered in the grid for the currently selected tab. */
const visibleActions = computed<ActionDefinition[]>(() => {
  if (activeTab.value === ALL_TAB) {
    return searchedActions.value;
  }

  return searchedActions.value.filter((action) => categoryOf(action) === activeTab.value);
});

function selectAction(action: ActionDefinition) {
  emit('select', action);
}
</script>

<template>
  <Teleport to="body">
    <Transition name="action-library-modal">
      <div
        v-if="open"
        class="fixed inset-0 z-[9999] flex items-center justify-center px-4 py-4 sm:py-8"
        role="dialog"
        aria-modal="true"
      >
        <button
          class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
          type="button"
          :aria-label="__('Close panel', textDomain)"
          @click="$emit('close')"
        />

        <div class="relative z-10 flex max-h-[calc(100dvh-4rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/20 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.25)]">
          <!-- Header -->
          <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
            <div>
              <h2 class="text-xl font-semibold tracking-tight text-slate-900">
                {{ title }}
              </h2>
              <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                {{ __('Browse the action library by category and pick a step to add to your workflow.', textDomain) }}
              </p>
            </div>

            <button
              type="button"
              class="btn-close box-content flex h-4 w-4 shrink-0 items-center justify-center rounded border-0 bg-transparent p-[0.25rem] opacity-50 transition hover:opacity-75 focus:opacity-100 focus:outline-none"
              :style="closeButtonStyle"
              :aria-label="__('Close panel', textDomain)"
              @click="$emit('close')"
            >
              <span class="sr-only">{{ __('Close', textDomain) }}</span>
            </button>
          </div>

          <!-- Category tabs -->
          <div class="border-b border-slate-200 px-6">
            <div class="-mb-px flex gap-1 overflow-x-auto">
              <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="group flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium transition"
                :class="activeTab === tab.id
                  ? 'border-primary-700 text-primary-700'
                  : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                @click="activeTab = tab.id"
              >
                <span
                  v-if="tab.iconSvg"
                  class="flex h-4 w-4 items-center justify-center"
                  v-html="tab.iconSvg"
                />
                <span>{{ tab.label }}</span>
                <span
                  class="rounded-full px-1.5 py-0.5 text-xs font-semibold transition"
                  :class="activeTab === tab.id ? 'bg-primary-50 text-primary-700' : 'bg-slate-100 text-slate-500'"
                >
                  {{ tab.count }}
                </span>
              </button>
            </div>
          </div>

          <!-- Search -->
          <div class="px-6 pt-5">
            <label class="block">
              <span class="sr-only">{{ __('Search actions', textDomain) }}</span>
              <input
                v-model="query"
                type="search"
                :placeholder="__('Search actions', textDomain)"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-primary-700 focus:bg-white focus:ring-4 focus:ring-primary-700/10"
              />
            </label>
          </div>

          <!-- Body -->
          <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
            <template v-if="loading">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div
                  v-for="index in 6"
                  :key="`action-skeleton-${index}`"
                  class="joinotify-skeleton flex items-start gap-3 rounded-[14px] border border-slate-200 bg-white p-4"
                >
                  <div class="joinotify-skeleton mt-0.5 h-10 w-10 rounded-xl bg-slate-200/70" />
                  <div class="min-w-0 flex-1">
                    <div class="joinotify-skeleton h-4 w-28 rounded-full bg-slate-200/75" />
                    <div class="joinotify-skeleton mt-3 h-3 w-40 rounded-full bg-slate-200/60" />
                  </div>
                </div>
              </div>
            </template>

            <template v-else>
              <div
                v-if="visibleActions.length"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"
              >
                <ActionLibraryCard
                  v-for="action in visibleActions"
                  :key="action.action"
                  :action="action"
                  @click="selectAction"
                />
              </div>

              <div
                v-else
                class="rounded-[14px] border border-dashed border-slate-300 px-4 py-12 text-center"
              >
                <p class="text-sm font-medium text-slate-700">{{ __('No actions available.', textDomain) }}</p>
                <p class="mt-1 text-sm leading-6 text-slate-500">
                  {{ __('Check the backend registry, the selected context, or the search term.', textDomain) }}
                </p>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.action-library-modal-enter-active,
.action-library-modal-leave-active {
  transition: opacity 0.24s ease;
}

.action-library-modal-enter-active > .relative,
.action-library-modal-leave-active > .relative {
  transition: transform 0.24s ease, opacity 0.24s ease;
}

.action-library-modal-enter-from,
.action-library-modal-leave-to {
  opacity: 0;
}

.action-library-modal-enter-from > .relative,
.action-library-modal-leave-to > .relative {
  transform: scale(0.97) translateY(8px);
  opacity: 0;
}
</style>
