<script setup>

/**
 * IntegrationsSettingsSection.vue frontend component.
 *
 * Renders the Applications cards with a top toolbar that lets the user filter by
 * category and switch the display mode. By default every application is shown in
 * a single flat grid ("Show all"); the user can group the cards into stacked
 * category sections or narrow the list to a single category. Category chips and
 * section headers use Boxicons glyphs mapped from the category id, falling back
 * to the backend-provided SVG (or a generic grid icon) for unregistered ones.
 *
 * @since 1.4.7
 * @version 2.1.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import {
  MessageDots,
  Brain,
  Cart,
  EditAlt,
  News,
  Shield,
  CodeAlt,
  Package,
  Grid,
} from '@boxicons/vue';
import IntegrationCard from '../cards/IntegrationCard.vue';

const props = defineProps({
  integrations: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['toggle', 'configure']);

const FALLBACK_CATEGORY = 'others';
const ALL = 'all';

/** Boxicons glyph mapped from the category id. */
const CATEGORY_ICONS = {
  channels: MessageDots,
  ai: Brain,
  ecommerce: Cart,
  forms: EditAlt,
  content: News,
  security: Shield,
  developer: CodeAlt,
  others: Package,
};

const activeCategory = ref(ALL);
const displayMode = ref('flat'); // 'flat' | 'grouped'

function isEnabled(key) {
  return (props.settings[key] || 'no') === 'yes';
}

function categoryOf(card) {
  return String(card?.category || '').trim() || FALLBACK_CATEGORY;
}

function humanizeCategory(id) {
  return String(id || '')
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

/** Boxicons component for a category id, or null when there is no mapping. */
function boxiconFor(id) {
  return CATEGORY_ICONS[id] || null;
}

/** Category metadata keyed by id, preserving the backend priority order. */
const categoryMeta = computed(() => {
  const map = new Map();

  props.categories.forEach((category) => {
    const id = String(category?.id || '').trim();

    if (id && !map.has(id)) {
      map.set(id, {
        id,
        label: String(category.label || humanizeCategory(id)),
        icon: String(category.icon || ''),
        priority: Number.isFinite(category.priority) ? category.priority : 0,
      });
    }
  });

  return map;
});

/**
 * All non-empty category sections in catalog order. Registered categories keep
 * their priority order; unregistered ones are appended afterwards.
 */
const allSections = computed(() => {
  const grouped = new Map();

  props.integrations.forEach((card) => {
    const id = categoryOf(card);

    if (!grouped.has(id)) {
      grouped.set(id, []);
    }

    grouped.get(id).push(card);
  });

  const ordered = [];
  const used = new Set();

  categoryMeta.value.forEach((meta, id) => {
    if (grouped.has(id)) {
      ordered.push({ ...meta, integrations: grouped.get(id) });
      used.add(id);
    }
  });

  grouped.forEach((cards, id) => {
    if (used.has(id)) {
      return;
    }

    ordered.push({
      id,
      label: humanizeCategory(id),
      icon: '',
      priority: Number.MAX_SAFE_INTEGER,
      integrations: cards,
    });
  });

  return ordered;
});

/** Filter chips: an "All" entry followed by every non-empty category. */
const filterChips = computed(() => [
  { id: ALL, label: __('All', textDomain), icon: '', count: props.integrations.length },
  ...allSections.value.map((section) => ({
    id: section.id,
    label: section.label,
    icon: section.icon,
    count: section.integrations.length,
  })),
]);

/** Sections to render, honoring the active category filter. */
const visibleSections = computed(() => {
  if (activeCategory.value !== ALL) {
    return allSections.value.filter((section) => section.id === activeCategory.value);
  }

  return allSections.value;
});

/** Flat list of cards when not grouping. */
const flatIntegrations = computed(() =>
  visibleSections.value.flatMap((section) => section.integrations)
);

/**
 * Grouped view is used when the display mode is "grouped" and no single category
 * is selected. A single-category filter always renders as one flat grid.
 */
const isGrouped = computed(() => displayMode.value === 'grouped' && activeCategory.value === ALL);

const activeSection = computed(() => visibleSections.value[0] || null);

const displayModes = computed(() => [
  { value: 'flat', label: __('Show all', textDomain) },
  { value: 'grouped', label: __('Group by category', textDomain) },
]);
</script>

<template>
  <div>
    <div class="mb-8 flex flex-col gap-4 border-b border-slate-200 pb-6 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="chip in filterChips"
          :key="chip.id"
          type="button"
          class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-50"
          :class="chip.id === activeCategory
            ? 'border-primary-200 bg-primary-50 text-primary-800'
            : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
          :aria-pressed="chip.id === activeCategory"
          @click="activeCategory = chip.id"
        >
          <component :is="boxiconFor(chip.id)" v-if="chip.id !== ALL && boxiconFor(chip.id)" width="18" height="18" />
          <span v-else-if="chip.id !== ALL && chip.icon" class="inline-flex [&_svg]:h-[18px] [&_svg]:w-[18px]" v-html="chip.icon" />
          <component :is="Grid" v-else width="18" height="18" />
          <span>{{ chip.label }}</span>
          <span
            class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold"
            :class="chip.id === activeCategory ? 'bg-primary-100 text-primary-800' : 'bg-slate-100 text-slate-500'"
          >
            {{ chip.count }}
          </span>
        </button>
      </div>

      <div
        class="inline-flex shrink-0 items-center gap-1 self-start rounded-full border border-slate-200 p-1 lg:self-auto"
        role="group"
        :aria-label="__('Display mode', textDomain)"
      >
        <button
          v-for="mode in displayModes"
          :key="mode.value"
          type="button"
          class="rounded-full px-4 py-1.5 text-sm font-medium transition focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
          :class="mode.value === displayMode
            ? 'bg-primary-700 text-white shadow-sm'
            : 'text-slate-600 hover:text-primary-800'"
          :aria-pressed="mode.value === displayMode"
          :disabled="activeCategory !== ALL"
          :title="activeCategory !== ALL ? __('Clear the category filter to change the display mode.', textDomain) : ''"
          @click="displayMode = mode.value"
        >
          {{ mode.label }}
        </button>
      </div>
    </div>

    <!-- Grouped view: one stacked section per category -->
    <div v-if="isGrouped" class="space-y-12">
      <section v-for="section in visibleSections" :key="section.id">
        <header class="mb-6 flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 [&_svg]:h-[22px] [&_svg]:w-[22px]">
            <component :is="boxiconFor(section.id)" v-if="boxiconFor(section.id)" width="22" height="22" />
            <span v-else-if="section.icon" v-html="section.icon" />
            <component :is="Grid" v-else width="22" height="22" />
          </span>
          <div>
            <h3 class="text-lg font-semibold text-slate-700">{{ section.label }}</h3>
            <p class="text-[13px] text-slate-500">
              {{ section.integrations.length }} {{ section.integrations.length === 1 ? __('application', textDomain) : __('applications', textDomain) }}
            </p>
          </div>
        </header>

        <div class="grid gap-6 max-[1368px]:grid-cols-3 min-[1400px]:grid-cols-4">
          <IntegrationCard
            v-for="card in section.integrations"
            :key="card.slug"
            :card="card"
            :enabled="isEnabled(card.setting_key)"
            @toggle="$emit('toggle', card.setting_key)"
            @configure="$emit('configure', $event)"
          />
        </div>
      </section>
    </div>

    <!-- Flat view: every visible card in a single grid -->
    <div v-else>
      <header v-if="activeCategory !== ALL && activeSection" class="mb-6 flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 [&_svg]:h-[22px] [&_svg]:w-[22px]">
          <component :is="boxiconFor(activeSection.id)" v-if="boxiconFor(activeSection.id)" width="22" height="22" />
          <span v-else-if="activeSection.icon" v-html="activeSection.icon" />
          <component :is="Grid" v-else width="22" height="22" />
        </span>
        <h3 class="text-lg font-semibold text-slate-700">{{ activeSection.label }}</h3>
      </header>

      <div class="grid gap-6 max-[1368px]:grid-cols-3 min-[1400px]:grid-cols-4">
        <IntegrationCard
          v-for="card in flatIntegrations"
          :key="card.slug"
          :card="card"
          :enabled="isEnabled(card.setting_key)"
          @toggle="$emit('toggle', card.setting_key)"
          @configure="$emit('configure', $event)"
        />
      </div>
    </div>
  </div>
</template>
