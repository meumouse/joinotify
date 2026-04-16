<script setup lang="ts">
/**
 * NodeConfigModal.vue
 *
 * Configuration modal for flow nodes. Each node type renders its own
 * specific fields. Adapted from the React builder's NodeConfigModal.tsx.
 *
 * @since 1.4.7
 */
import { ref, watch, computed } from 'vue';
import { getFlowNodeConfig } from './flowNodeTypes';

// ── Props / Emits ──────────────────────────────────────────────────────────

const props = defineProps<{
  open: boolean;
  nodeType: string;
  label: string;
  description: string;
  config: Record<string, unknown>;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'save', data: { label: string; description: string; config: Record<string, unknown> }): void;
}>();

// ── Local draft state ──────────────────────────────────────────────────────

const draftLabel = ref(props.label);
const draftDescription = ref(props.description);
const draftConfig = ref<Record<string, unknown>>({ ...props.config });

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      draftLabel.value = props.label;
      draftDescription.value = props.description;
      draftConfig.value = { ...props.config };
    }
  },
);

function updateConfig(key: string, value: unknown) {
  draftConfig.value = { ...draftConfig.value, [key]: value };
}

function handleSave() {
  emit('save', {
    label: draftLabel.value,
    description: draftDescription.value,
    config: { ...draftConfig.value },
  });
  emit('close');
}

// ── Emoji picker (WhatsApp fields) ────────────────────────────────────────

const showEmoji = ref(false);
const emojiTarget = ref<'message' | 'caption'>('message');

const COMMON_EMOJIS = [
  '😀', '😂', '❤️', '👍', '🎉', '🔥', '✅', '⭐', '💡', '📌',
  '🚀', '💬', '📎', '📢', '🙏', '👋', '💪', '🤝', '📞', '✨',
];

function insertEmoji(emoji: string) {
  if (emojiTarget.value === 'caption') {
    updateConfig('caption', String(draftConfig.value.caption ?? '') + emoji);
  } else {
    updateConfig('message', String(draftConfig.value.message ?? '') + emoji);
  }
  showEmoji.value = false;
}

// ── Condition rows ────────────────────────────────────────────────────────

interface Condition {
  field: string;
  operator: string;
  value: string;
}

const conditions = computed<Condition[]>(() => {
  const raw = draftConfig.value.conditions;
  return Array.isArray(raw) && raw.length
    ? (raw as Condition[])
    : [{ field: '', operator: 'equals', value: '' }];
});

function updateCondition(index: number, key: keyof Condition, value: string) {
  const updated = conditions.value.map((c, i) =>
    i === index ? { ...c, [key]: value } : c,
  );
  updateConfig('conditions', updated);
}

function addCondition() {
  updateConfig('conditions', [...conditions.value, { field: '', operator: 'equals', value: '' }]);
}

function removeCondition(index: number) {
  updateConfig('conditions', conditions.value.filter((_, i) => i !== index));
}

// ── Misc ──────────────────────────────────────────────────────────────────

const nodeConfig = computed(() => getFlowNodeConfig(props.nodeType));

const WAIT_UNITS = [
  { value: 'seconds', label: 'Segundos' },
  { value: 'minutes', label: 'Minutos' },
  { value: 'hours', label: 'Horas' },
  { value: 'days', label: 'Dias' },
  { value: 'weeks', label: 'Semanas' },
  { value: 'months', label: 'Meses' },
  { value: 'years', label: 'Anos' },
];

const MEDIA_TYPES = [
  { value: 'image', label: 'Imagem', icon: 'bx-image' },
  { value: 'video', label: 'Vídeo', icon: 'bx-video' },
  { value: 'document', label: 'Documento', icon: 'bx-file' },
  { value: 'audio', label: 'Áudio', icon: 'bx-music' },
];

const OPERATORS = [
  { value: 'equals', label: 'Igual a' },
  { value: 'not_equals', label: 'Diferente de' },
  { value: 'contains', label: 'Contém' },
  { value: 'not_contains', label: 'Não contém' },
  { value: 'starts_with', label: 'Começa com' },
  { value: 'ends_with', label: 'Termina com' },
  { value: 'greater_than', label: 'Maior que' },
  { value: 'less_than', label: 'Menor que' },
  { value: 'is_empty', label: 'Está vazio' },
  { value: 'is_not_empty', label: 'Não está vazio' },
];

// Hours/minutes arrays for wait-date
const HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const MINUTES = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto px-4 py-6"
    >
      <!-- Backdrop -->
      <button
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        type="button"
        aria-label="Fechar"
        @click="$emit('close')"
      />

      <!-- Dialog -->
      <div
        class="relative z-10 w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-xl border border-white/20 bg-white shadow-2xl"
      >
        <!-- Header -->
        <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white px-6 py-5">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <div
                class="flex items-center justify-center w-6 h-6 rounded-md shrink-0"
                :class="nodeConfig?.color ?? 'bg-slate-400'"
              >
                <i :class="`bx ${nodeConfig?.icon ?? 'bx-cog'} text-white`" style="font-size: 12px;" />
              </div>
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                Configurações
              </p>
            </div>
            <h3 class="text-xl font-semibold text-slate-900">{{ label }}</h3>
          </div>
          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors"
            aria-label="Fechar"
            @click="$emit('close')"
          >
            <i class="bx bx-x" style="font-size: 20px;" />
          </button>
        </div>

        <!-- Body -->
        <div class="space-y-6 px-6 py-5">
          <!-- Common: label + description -->
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Nome da ação</label>
              <input
                v-model="draftLabel"
                type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Descrição</label>
              <input
                v-model="draftDescription"
                type="text"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
              />
            </div>
          </div>

          <div class="border-t border-slate-100 pt-5">

            <!-- ── php-snippet ────────────────────────────── -->
            <template v-if="nodeType === 'php-snippet'">
              <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Código PHP</label>
                <div class="rounded-lg border border-slate-200 overflow-hidden">
                  <textarea
                    :value="String(draftConfig.code ?? '<?php\n\n// Seu código aqui\n')"
                    rows="12"
                    spellcheck="false"
                    class="w-full bg-slate-900 px-4 py-3 text-sm font-mono text-emerald-400 outline-none resize-none"
                    @input="updateConfig('code', ($event.target as HTMLTextAreaElement).value)"
                  />
                </div>
                <p class="text-xs text-slate-400">
                  Insira o código PHP que será executado nesta etapa.
                </p>
              </div>
            </template>

            <!-- ── wait-time ──────────────────────────────── -->
            <template v-else-if="nodeType === 'wait-time'">
              <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Tempo de espera</label>
                <div class="flex gap-3">
                  <input
                    type="number"
                    min="1"
                    placeholder="Ex: 30"
                    :value="draftConfig.duration ?? ''"
                    class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('duration', ($event.target as HTMLInputElement).value)"
                  />
                  <select
                    :value="String(draftConfig.unit ?? 'minutes')"
                    class="w-44 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @change="updateConfig('unit', ($event.target as HTMLSelectElement).value)"
                  >
                    <option v-for="u in WAIT_UNITS" :key="u.value" :value="u.value">{{ u.label }}</option>
                  </select>
                </div>
              </div>
            </template>

            <!-- ── wait-date ──────────────────────────────── -->
            <template v-else-if="nodeType === 'wait-date'">
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Data</label>
                  <input
                    type="date"
                    :value="String(draftConfig.date ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @change="updateConfig('date', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Hora</label>
                    <select
                      :value="String(draftConfig.hour ?? '12')"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @change="updateConfig('hour', ($event.target as HTMLSelectElement).value)"
                    >
                      <option v-for="h in HOURS" :key="h" :value="h">{{ h }}h</option>
                    </select>
                  </div>
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Minuto</label>
                    <select
                      :value="String(draftConfig.minute ?? '00')"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @change="updateConfig('minute', ($event.target as HTMLSelectElement).value)"
                    >
                      <option v-for="m in MINUTES" :key="m" :value="m">{{ m }}min</option>
                    </select>
                  </div>
                </div>
              </div>
            </template>

            <!-- ── whatsapp-text ───────────────────────────── -->
            <template v-else-if="nodeType === 'whatsapp-text'">
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Remetente</label>
                  <input
                    type="text"
                    placeholder="Número ou {{sender}}"
                    :value="String(draftConfig.sender ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('sender', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Destinatário</label>
                  <input
                    type="text"
                    placeholder="Ex: {{contato.telefone}} ou +5511999990000"
                    :value="String(draftConfig.recipient ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('recipient', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Mensagem</label>
                  <!-- Formatting toolbar -->
                  <div class="flex items-center gap-1 rounded-t-lg border border-b-0 border-slate-200 bg-slate-50 px-2 py-1.5">
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors" title="Negrito"><i class="bx bx-bold" style="font-size:14px;" /></button>
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors" title="Itálico"><i class="bx bx-italic" style="font-size:14px;" /></button>
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors" title="Tachado"><i class="bx bx-strikethrough" style="font-size:14px;" /></button>
                    <div class="mx-1 h-4 w-px bg-slate-200" />
                    <div class="relative">
                      <button
                        type="button"
                        class="rounded p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors"
                        title="Emoji"
                        @click="emojiTarget = 'message'; showEmoji = !showEmoji"
                      >
                        <i class="bx bx-smile" style="font-size:14px;" />
                      </button>
                      <div
                        v-if="showEmoji && emojiTarget === 'message'"
                        class="absolute left-0 top-full z-50 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-xl"
                      >
                        <div class="grid grid-cols-10 gap-1">
                          <button
                            v-for="emoji in COMMON_EMOJIS"
                            :key="emoji"
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded text-lg hover:bg-slate-100 transition-colors"
                            @click="insertEmoji(emoji)"
                          >{{ emoji }}</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <textarea
                    rows="5"
                    placeholder="Digite sua mensagem... Use {{placeholders}}"
                    :value="String(draftConfig.message ?? '')"
                    class="w-full rounded-b-lg rounded-t-none border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10 resize-none"
                    @input="updateConfig('message', ($event.target as HTMLTextAreaElement).value)"
                  />
                </div>
              </div>
            </template>

            <!-- ── whatsapp-media ──────────────────────────── -->
            <template v-else-if="nodeType === 'whatsapp-media'">
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Remetente</label>
                  <input
                    type="text"
                    placeholder="Número ou {{sender}}"
                    :value="String(draftConfig.sender ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('sender', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Destinatário</label>
                  <input
                    type="text"
                    placeholder="Ex: {{contato.telefone}} ou +5511999990000"
                    :value="String(draftConfig.recipient ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('recipient', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Tipo de mídia</label>
                    <select
                      :value="String(draftConfig.mediaType ?? 'image')"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @change="updateConfig('mediaType', ($event.target as HTMLSelectElement).value)"
                    >
                      <option v-for="mt in MEDIA_TYPES" :key="mt.value" :value="mt.value">{{ mt.label }}</option>
                    </select>
                  </div>
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">URL da mídia</label>
                    <input
                      type="url"
                      placeholder="https://exemplo.com/arquivo.jpg"
                      :value="String(draftConfig.mediaUrl ?? '')"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @input="updateConfig('mediaUrl', ($event.target as HTMLInputElement).value)"
                    />
                  </div>
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Legenda</label>
                  <div class="flex items-center gap-1 rounded-t-lg border border-b-0 border-slate-200 bg-slate-50 px-2 py-1.5">
                    <div class="relative">
                      <button
                        type="button"
                        class="rounded p-1 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors"
                        title="Emoji"
                        @click="emojiTarget = 'caption'; showEmoji = !showEmoji"
                      >
                        <i class="bx bx-smile" style="font-size:14px;" />
                      </button>
                      <div
                        v-if="showEmoji && emojiTarget === 'caption'"
                        class="absolute left-0 top-full z-50 mt-1 rounded-lg border border-slate-200 bg-white p-2 shadow-xl"
                      >
                        <div class="grid grid-cols-10 gap-1">
                          <button
                            v-for="emoji in COMMON_EMOJIS"
                            :key="emoji"
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded text-lg hover:bg-slate-100 transition-colors"
                            @click="insertEmoji(emoji)"
                          >{{ emoji }}</button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <textarea
                    rows="3"
                    placeholder="Legenda da mídia..."
                    :value="String(draftConfig.caption ?? '')"
                    class="w-full rounded-b-lg rounded-t-none border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10 resize-none"
                    @input="updateConfig('caption', ($event.target as HTMLTextAreaElement).value)"
                  />
                </div>
              </div>
            </template>

            <!-- ── condition ───────────────────────────────── -->
            <template v-else-if="nodeType === 'condition'">
              <div class="space-y-4">
                <label class="block text-sm font-medium text-slate-700">Condições</label>

                <div
                  v-for="(cond, i) in conditions"
                  :key="i"
                  class="flex items-end gap-2"
                >
                  <div class="flex-1 space-y-1">
                    <span v-if="i === 0" class="text-xs text-slate-400">Campo</span>
                    <input
                      type="text"
                      placeholder="Ex: email"
                      :value="cond.field"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @input="updateCondition(i, 'field', ($event.target as HTMLInputElement).value)"
                    />
                  </div>
                  <div class="w-[160px] space-y-1">
                    <span v-if="i === 0" class="text-xs text-slate-400">Operador</span>
                    <select
                      :value="cond.operator"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @change="updateCondition(i, 'operator', ($event.target as HTMLSelectElement).value)"
                    >
                      <option v-for="op in OPERATORS" :key="op.value" :value="op.value">{{ op.label }}</option>
                    </select>
                  </div>
                  <div class="flex-1 space-y-1">
                    <span v-if="i === 0" class="text-xs text-slate-400">Valor</span>
                    <input
                      type="text"
                      placeholder="Valor"
                      :value="cond.value"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @input="updateCondition(i, 'value', ($event.target as HTMLInputElement).value)"
                    />
                  </div>
                  <button
                    type="button"
                    class="mb-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 transition-colors"
                    @click="removeCondition(i)"
                  >
                    <i class="bx bx-x" style="font-size: 18px;" />
                  </button>
                </div>

                <button
                  type="button"
                  class="flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:border-primary-400 hover:text-primary-600 transition-colors"
                  @click="addCondition"
                >
                  <i class="bx bx-plus" style="font-size:14px;" />
                  Adicionar condição
                </button>
              </div>
            </template>

            <!-- ── stop ───────────────────────────────────── -->
            <template v-else-if="nodeType === 'stop'">
              <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-4">
                <p class="text-sm font-medium text-red-700">
                  Nenhuma ação será executada ao chegar neste ponto.
                </p>
                <p class="mt-1 text-xs text-red-500">
                  O fluxo será interrompido completamente nesta etapa.
                </p>
              </div>
            </template>

            <!-- ── default / trigger ──────────────────────── -->
            <template v-else>
              <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Descrição</label>
                <textarea
                  v-model="draftDescription"
                  rows="4"
                  placeholder="Descreva esta etapa..."
                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10 resize-none"
                />
              </div>
            </template>
          </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 z-10 flex items-center justify-end gap-3 border-t border-slate-100 bg-white px-6 py-4">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
            @click="$emit('close')"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors"
            @click="handleSave"
          >
            Salvar configurações
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
