<script setup>
/**
 * TemplateCard.vue
 *
 * Presentational card for a single workflow template in the template library.
 * Displays an integration icon and badge, the template title and description,
 * the trigger it responds to, and a full-width import button whose label and
 * enabled/loading state reflect the template's availability and the current
 * import progress. Emits a click event when the import button is pressed.
 *
 * @since 2.0.0
 */
import { computed } from 'vue';
import { __, textDomain } from '../../utils/i18n';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  category: { type: String, default: '' },
  integration: { type: String, default: '' },
  trigger: { type: String, default: '' },
  available: { type: Boolean, default: false },
  importing: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});

defineEmits(['click']);

/**
 * Visual treatment (icon glyph + color palette) for the template's integration.
 *
 * Keys off the raw context/category slug so brand-specific integrations get a
 * recognizable icon and accent color, falling back to a neutral generic look
 * for anything unmapped.
 *
 * @since 2.0.0
 * @returns {{icon: string, iconBg: string, iconColor: string, dot: string}}
 */
const visual = computed(() => {
  const map = {
    wordpress: { icon: 'wordpress', iconBg: 'bg-primary-50', iconColor: 'text-primary-700', dot: 'bg-primary-600' },
    woocommerce: { icon: 'cart', iconBg: 'bg-purple-50', iconColor: 'text-purple-600', dot: 'bg-purple-500' },
    elementor: { icon: 'generic', iconBg: 'bg-rose-50', iconColor: 'text-rose-500', dot: 'bg-rose-500' },
    wpforms: { icon: 'generic', iconBg: 'bg-orange-50', iconColor: 'text-orange-500', dot: 'bg-orange-500' },
    flexify_checkout: { icon: 'cart', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600', dot: 'bg-emerald-500' },
  };

  return map[props.category] || { icon: 'generic', iconBg: 'bg-slate-100', iconColor: 'text-slate-500', dot: 'bg-slate-400' };
});

/**
 * Whether the import button should be blocked from interaction.
 *
 * @since 2.0.0
 * @returns {boolean} True when the template is unavailable or another import is running.
 */
const isDisabled = computed(() => !props.available || (props.busy && !props.importing));
</script>

<template>
  <article
    class="group flex h-full flex-col rounded-2xl border border-slate-200/80 bg-white p-6 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-[0_18px_40px_rgba(15,23,42,0.08)]"
  >
    <div class="flex items-center justify-between gap-3">
      <span class="flex h-11 w-11 items-center justify-center rounded-xl" :class="visual.iconBg">
        <!-- WordPress -->
        <svg v-if="visual.icon === 'wordpress'" class="h-6 w-6" :class="visual.iconColor" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M12 0C5.385 0 0 5.385 0 12s5.385 12 12 12 12-5.385 12-12S18.615 0 12 0zM1.211 12c0-1.564.336-3.05.935-4.39l5.15 14.107C3.694 19.96 1.212 16.271 1.211 12zM12 22.784c-1.059 0-2.081-.153-3.048-.437l3.237-9.406 3.315 9.087c.024.053.05.101.078.149-1.12.393-2.325.609-3.582.609zm1.488-15.855c.647-.03 1.232-.105 1.232-.105.582-.075.514-.93-.067-.899 0 0-1.755.135-2.88.135-1.064 0-2.85-.15-2.85-.15-.585-.03-.661.855-.075.885 0 0 .54.061 1.125.09l1.68 4.605-2.37 7.08L5.354 6.9c.649-.03 1.234-.1 1.234-.1.585-.075.516-.93-.065-.896 0 0-1.746.138-2.874.138-.2 0-.438-.008-.69-.015C4.911 3.15 8.235 1.215 12 1.215c2.809 0 5.365 1.072 7.286 2.833-.046-.003-.091-.009-.141-.009-1.06 0-1.812.923-1.812 1.914 0 .89.513 1.643 1.06 2.531.411.72.89 1.643.89 2.977 0 .915-.354 1.994-.821 3.479l-1.075 3.585-3.9-11.61zm7.981.896c.84 1.537 1.318 3.3 1.318 5.175 0 3.979-2.156 7.456-5.363 9.325l3.295-9.527c.615-1.54.82-2.771.82-3.864 0-.405-.026-.78-.07-1.11z" />
        </svg>
        <!-- WooCommerce / commerce -->
        <svg v-else-if="visual.icon === 'cart'" class="h-6 w-6" :class="visual.iconColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="9" cy="21" r="1" />
          <circle cx="20" cy="21" r="1" />
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
        </svg>
        <!-- Generic integration -->
        <svg v-else class="h-6 w-6" :class="visual.iconColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M14 4v4a2 2 0 0 0 2 2h4" />
          <rect x="4" y="4" width="16" height="16" rx="3" />
          <path d="M9 13h6M9 17h4" />
        </svg>
      </span>

      <span
        v-if="integration"
        class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200/70"
      >
        <span class="h-1.5 w-1.5 rounded-full" :class="visual.dot" aria-hidden="true" />
        {{ integration }}
      </span>
    </div>

    <h3 class="mt-5 text-lg font-bold leading-6 tracking-tight text-slate-900">
      {{ title }}
    </h3>

    <p v-if="description" class="mt-2 line-clamp-2 min-h-[2.75rem] text-sm leading-6 text-slate-500">
      {{ description }}
    </p>

    <div class="mt-auto">
      <div class="mt-5 border-t border-dashed border-slate-200" />

      <div class="mt-4 flex items-baseline gap-2 text-sm leading-6">
        <span class="text-slate-400">{{ __('Trigger:', textDomain) }}</span>
        <span class="font-medium text-slate-700">{{ trigger || __('No trigger', textDomain) }}</span>
      </div>

      <button
        type="button"
        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-slate-200 disabled:hover:bg-white"
        :disabled="isDisabled"
        @click="$emit('click')"
      >
        <span
          v-if="importing"
          class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent"
          aria-hidden="true"
        />
        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
          <polyline points="7 10 12 15 17 10" />
          <line x1="12" y1="15" x2="12" y2="3" />
        </svg>
        <span>{{ importing ? __('Importing...', textDomain) : __('Import workflow', textDomain) }}</span>
      </button>
    </div>
  </article>
</template>
