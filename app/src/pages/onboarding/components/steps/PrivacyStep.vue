<script setup>

/**
 * PrivacyStep.vue frontend component.
 *
 * Asks for consent to share anonymous usage data. The answer defaults to "no",
 * and the exact payload is shown on demand — agreeing to something you cannot
 * read is not consent.
 *
 * @since 2.3.0
 */
import { computed, ref } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';
import ToggleSwitch from '../../../../components/toggles/ToggleSwitch.vue';

const props = defineProps({
  modelValue: { type: String, default: 'no' },
  telemetry: { type: Object, default: () => ({}) },
});

defineEmits(['update:modelValue']);

const detailsOpen = ref(false);

const neverCollected = computed(() => props.telemetry?.never_collected || []);
const payloadPreview = computed(() => JSON.stringify(props.telemetry?.collected || {}, null, 2));
const identifiedBy = computed(() => props.telemetry?.identified_by || '');
const sampleEvents = computed(() => JSON.stringify(props.telemetry?.sample_events || [], null, 2));
</script>

<template>
  <div class="space-y-6">
    <p class="max-w-2xl text-sm leading-6 text-muted">
      {{ __('Knowing which WordPress and PHP versions Joinotify actually runs on, and which errors people hit, is what tells us where to spend our time. Sharing is entirely up to you and the plugin behaves identically either way.', textDomain) }}
    </p>

    <div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <h3 class="text-[15px] font-semibold text-ink">{{ __('Share anonymous usage data', textDomain) }}</h3>
          <p class="mt-1 max-w-lg text-[13px] leading-5 text-slate-500">
            {{ __('Off by default. You can change this at any time in Settings → About.', textDomain) }}
          </p>
        </div>

        <ToggleSwitch
          :model-value="modelValue"
          true-value="yes"
          false-value="no"
          size="md"
          :aria-label="__('Share anonymous usage data', textDomain)"
          @update:model-value="$emit('update:modelValue', $event)"
        />
      </div>

      <div class="mt-5 border-t border-slate-100 pt-5">
        <button
          type="button"
          class="text-[13px] font-semibold text-primary-700 underline underline-offset-4"
          :aria-expanded="detailsOpen"
          @click="detailsOpen = !detailsOpen"
        >
          {{ detailsOpen ? __('Hide what would be sent', textDomain) : __('See exactly what would be sent', textDomain) }}
        </button>

        <div v-if="detailsOpen" class="mt-4 space-y-4">
          <!--
            Stated before the "never collected" list, not after it. On its own that list
            reads as "nothing identifies this site", which is not quite true: the report
            travels on the same API key the site uses to send messages, so the service
            knows the account. Putting the qualifier second would let the stronger claim
            land first.
          -->
          <div v-if="identifiedBy">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ __('How this site is identified', textDomain) }}
            </p>

            <p class="mt-2 text-[13px] leading-5 text-slate-600">{{ identifiedBy }}</p>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ __('Never collected', textDomain) }}
            </p>

            <ul class="mt-2 space-y-2 text-[13px] leading-5 text-slate-600">
              <li v-for="item in neverCollected" :key="item" class="flex gap-2">
                <span aria-hidden="true" class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" />
                <span>{{ item }}</span>
              </li>
            </ul>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ __('The exact payload for this site right now', textDomain) }}
            </p>

            <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-slate-900 p-4 text-[12px] leading-5 text-slate-100"><code>{{ payloadPreview }}</code></pre>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-shell-500">
              {{ __('Examples of the events that would be sent', textDomain) }}
            </p>

            <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-slate-900 p-4 text-[12px] leading-5 text-slate-100"><code>{{ sampleEvents }}</code></pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
