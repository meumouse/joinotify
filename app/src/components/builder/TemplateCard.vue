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
import { ArrowToBottomStroke, Cart, FileDetail, Wordpress } from '@boxicons/vue';

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  category: { type: String, default: '' },
  integration: { type: String, default: '' },
  icon: { type: String, default: '' },
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
      <!-- Registered integration icon (brand SVG rendered on the settings screen) -->
      <span
        v-if="icon"
        class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-50 text-slate-700 [&>svg]:h-6 [&>svg]:w-6"
        v-html="icon"
      />
      <span v-else class="flex h-11 w-11 items-center justify-center rounded-xl" :class="visual.iconBg">
        <!-- WordPress -->
        <Wordpress v-if="visual.icon === 'wordpress'" pack="brands" class="h-6 w-6" :class="visual.iconColor" aria-hidden="true" />
        <!-- WooCommerce / commerce -->
        <Cart v-else-if="visual.icon === 'cart'" class="h-6 w-6" :class="visual.iconColor" aria-hidden="true" />
        <!-- Generic integration -->
        <FileDetail v-else class="h-6 w-6" :class="visual.iconColor" aria-hidden="true" />
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
        <ArrowToBottomStroke v-else class="h-4 w-4" aria-hidden="true" />
        <span>{{ importing ? __('Importing...', textDomain) : __('Import workflow', textDomain) }}</span>
      </button>
    </div>
  </article>
</template>
