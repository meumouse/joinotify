<script setup>

/**
 * IntegrationsSettingsSection.vue frontend component.
 *
 * Groups the Applications cards into stacked category sections, following the
 * backend-provided category catalog order (priority). Cards whose category is
 * not registered fall back to an "Others" section rendered last.
 *
 * @since 1.4.7
 * @version 2.1.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import IntegrationCard from '../cards/IntegrationCard.vue';

const props = defineProps({
  integrations: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) },
});

defineEmits(['toggle', 'configure']);

const FALLBACK_CATEGORY = 'others';

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
 * Visible sections: only categories that currently have at least one card.
 * Registered categories keep their catalog order; unregistered ones are
 * appended afterwards (in first-seen order), mirroring the builder library.
 */
const sections = computed(() => {
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

/** When there is a single section we skip the header to avoid redundancy. */
const showHeaders = computed(() => sections.value.length > 1);
</script>

<template>
  <div class="space-y-12">
    <section v-for="section in sections" :key="section.id">
      <header v-if="showHeaders" class="mb-6 flex items-center gap-3 border-b border-slate-200 pb-4">
        <span
          v-if="section.icon"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 [&_svg]:h-5 [&_svg]:w-5"
          v-html="section.icon"
        />
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
</template>
