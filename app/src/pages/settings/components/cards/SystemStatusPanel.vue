<script setup>

/**
 * SystemStatusPanel.vue frontend component.
 *
 * @since 1.4.7
 * @version 1.4.7
 */
import { computed } from 'vue';
import { __, textDomain } from '../../../../utils/i18n';

const props = defineProps({
  system: { type: Object, default: () => ({}) },
});

const wordpressItems = computed(() => props.system.wordpress || []);
const pluginItems = computed(() => props.system.plugin || []);
const serverItems = computed(() => props.system.server || []);

function statusClass(status) {
  const map = {
    success: 'bg-emerald-100 text-emerald-600',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-rose-100 text-rose-700',
    info: 'bg-slate-100 text-slate-600',
  };

  return map[status] || map.info;
}

function statusLabel(status) {
  if (status === 'success') return __('ok', textDomain);
  if (status === 'warning') return __('warning', textDomain);
  if (status === 'danger') return __('error', textDomain);
  return __('info', textDomain);
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">{{ __('System status:', textDomain) }}</h3>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div>
        <h4 class="text-[14px] font-semibold text-slate-700">{{ __('WordPress', textDomain) }}</h4>
        <div class="mt-4 space-y-3">
          <div
            v-for="item in wordpressItems"
            :key="item.label"
            class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2"
          >
            <div class="pr-4">
              <div class="text-[13px] text-slate-500">{{ item.label }}</div>
              <div class="text-[14px] font-medium text-slate-700">{{ item.value }}</div>
            </div>
            <span class="rounded-full px-3 py-1 text-[12px] font-semibold capitalize" :class="statusClass(item.status)">
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>
      </div>

      <div>
        <h4 class="text-[14px] font-semibold text-slate-700">{{ __('Joinotify', textDomain) }}</h4>
        <div class="mt-4 space-y-3">
          <div
            v-for="item in pluginItems"
            :key="item.label"
            class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2"
          >
            <div class="pr-4">
              <div class="text-[13px] text-slate-500">{{ item.label }}</div>
              <div class="text-[14px] font-medium text-slate-700">{{ item.value }}</div>
            </div>
            <span class="rounded-full px-3 py-1 text-[12px] font-semibold capitalize" :class="statusClass(item.status)">
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>
      </div>

      <div>
        <h4 class="text-[14px] font-semibold text-slate-700">{{ __('Server', textDomain) }}</h4>
        <div class="mt-4 space-y-3">
          <div
            v-for="item in serverItems"
            :key="item.label"
            class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2"
          >
            <div class="pr-4">
              <div class="text-[13px] text-slate-500">{{ item.label }}</div>
              <div class="text-[14px] font-medium text-slate-700">{{ item.value }}</div>
            </div>
            <span class="rounded-full px-3 py-1 text-[12px] font-semibold capitalize" :class="statusClass(item.status)">
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
