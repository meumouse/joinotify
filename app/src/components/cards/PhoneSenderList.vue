<template>
  <div class="space-y-6 w-[600px]">
    <div>
      <h3 class="text-[15px] font-semibold text-slate-800">Remetentes cadastrados</h3>
      <p class="mt-1 text-[13px] leading-5 text-slate-500">
        Telefone(s) já validados e disponíveis para uso nos fluxos.
      </p>
    </div>

    <div v-if="!senders.length" class="rounded-lg border border-dashed border-slate-200 bg-white px-4 py-5 text-[14px] text-slate-500">
      Nenhum remetente validado ainda.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="sender in senders"
        :key="sender.phone"
        class="flex flex-wrap items-center gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4"
      >
        <div class="min-w-[220px] flex-1">
          <div class="text-[14px] font-semibold text-slate-700">{{ sender.formatted || sender.phone }}</div>
        </div>

        <button
          type="button"
          class="rounded-full border border-slate-200 px-3 py-2 text-slate-500 transition hover:bg-slate-50"
          @click="$emit('refresh', sender.phone)"
          aria-label="Atualizar conexão"
        >
          ↻
        </button>

        <span
          class="rounded-full px-3 py-2 text-[13px] font-semibold"
          :class="sender.connection === 'connected' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500'"
        >
          {{ sender.connection === 'connected' ? 'Conectado' : 'Desconectado' }}
        </span>

        <button
          type="button"
          class="rounded-[8px] border border-rose-200 px-4 py-2 text-[14px] font-medium text-rose-400 transition hover:bg-rose-50"
          @click="$emit('remove', sender.phone)"
        >
          Remover
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  senders: { type: Array, default: () => [] },
});

defineEmits(['remove', 'refresh']);
</script>
