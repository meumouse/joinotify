<template>
  <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h3 class="text-base font-semibold text-rose-900">Zona de risco</h3>
        <p class="mt-2 text-sm leading-6 text-rose-700">Ações irreversíveis para limpeza da configuração e logs.</p>
      </div>
      <StatusBadge label="Irreversível" tone="danger" />
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      <button
        type="button"
        class="rounded-full bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-500"
        @click="$emit('reset')"
      >
        Redefinir configurações
      </button>
      <button
        type="button"
        class="rounded-full border border-rose-200 bg-white px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50"
        @click="$emit('clear-logs')"
      >
        Limpar logs
      </button>
    </div>
  </div>
</template>

<script setup>
import StatusBadge from '../base/StatusBadge.vue';

defineEmits(['reset', 'clear-logs']);
</script>
