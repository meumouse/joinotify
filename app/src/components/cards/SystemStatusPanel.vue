<template>
  <div class="space-y-6">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">Status do sistema:</h3>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div v-for="group in groups" :key="group.key">
        <h4 class="text-[14px] font-semibold text-slate-700">{{ group.label }}</h4>
        <div class="mt-4 space-y-3">
          <div
            v-for="item in group.items"
            :key="item.label"
            class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2"
          >
            <div class="pr-4">
              <div class="text-[13px] text-slate-500">{{ item.label }}</div>
              <div class="text-[14px] font-medium text-slate-700">{{ item.value }}</div>
            </div>
            <span
              class="rounded-full px-3 py-1 text-[12px] font-semibold capitalize"
              :class="statusClass(item.status)"
            >
              {{ statusLabel(item.status) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  system: { type: Object, default: () => ({}) },
});

const groups = computed(() => [
  { key: 'wordpress', label: 'WordPress', items: props.system.wordpress || [] },
  { key: 'plugin', label: 'Joinotify', items: props.system.plugin || [] },
  { key: 'server', label: 'Servidor', items: props.system.server || [] },
]);

function statusLabel(status) {
  const map = {
    success: 'ok',
    warning: 'atenção',
    danger: 'erro',
    info: 'info',
  };

  return map[status] || 'info';
}

function statusClass(status) {
  const map = {
    success: 'bg-emerald-100 text-emerald-600',
    warning: 'bg-amber-100 text-amber-700',
    danger: 'bg-rose-100 text-rose-700',
    info: 'bg-slate-100 text-slate-600',
  };

  return map[status] || map.info;
}
</script>
