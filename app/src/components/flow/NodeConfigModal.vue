<script setup lang="ts">
/**
 * NodeConfigModal.vue
 *
 * Lightweight configuration modal for flow nodes.
 *
 * @since 1.4.7
 */
import { computed, ref, watch } from 'vue';
import BaseRichTextArea from '../base/BaseRichTextArea.vue';
import SelectField from '../fields/SelectField.vue';
import { getFlowNodeConfig } from './flowNodeTypes';
import { useWorkflowBuilderStore } from '../../stores/useWorkflowBuilderStore';

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

const draftLabel = ref(props.label);
const draftDescription = ref(props.description);
const draftConfig = ref<Record<string, unknown>>({ ...props.config });
const store = useWorkflowBuilderStore();

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      return;
    }

    draftLabel.value = props.label;
    draftDescription.value = props.description;
    draftConfig.value = { ...props.config };
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
  const nextConditions = conditions.value.map((condition, currentIndex) =>
    currentIndex === index ? { ...condition, [key]: value } : condition,
  );
  updateConfig('conditions', nextConditions);
}

function addCondition() {
  updateConfig('conditions', [...conditions.value, { field: '', operator: 'equals', value: '' }]);
}

function removeCondition(index: number) {
  updateConfig('conditions', conditions.value.filter((_, currentIndex) => currentIndex !== index));
}

const normalizedNodeType = computed(() => String(props.nodeType || '').trim());
const senderSuggestions = computed(() => {
  const senders = Array.isArray(store.bootstrap?.phones?.senders)
    ? store.bootstrap.phones.senders
    : [];

  return senders
    .map((item) => {
      if (!item || typeof item !== 'object') {
        return '';
      }

      return String((item as Record<string, unknown>).phone || '').trim();
    })
    .filter(Boolean);
});
const senderSelectOptions = computed(() => {
  const options = senderSuggestions.value.map((senderPhone) => ({
    value: senderPhone,
    label: senderPhone,
  }));
  const currentSender = String(draftConfig.value.sender ?? '').trim();

  if (currentSender && !options.some((option) => option.value === currentSender)) {
    options.unshift({
      value: currentSender,
      label: `${currentSender} (atual)`,
    });
  }

  return options;
});
const senderField = computed(() => ({
  placeholder: senderSelectOptions.value.length ? 'Selecione um remetente' : 'Nenhum remetente registrado',
  searchable: senderSelectOptions.value.length > 8,
  emptyLabel: 'Nenhum remetente encontrado',
  options: senderSelectOptions.value,
}));
const nodeConfig = computed(() => getFlowNodeConfig(normalizedNodeType.value));
const isSnippetNode = computed(() => ['php-snippet', 'snippet_php'].includes(normalizedNodeType.value));
const isTimeDelayNode = computed(() => ['wait-time', 'time_delay'].includes(normalizedNodeType.value));
const isWaitDateNode = computed(() => normalizedNodeType.value === 'wait-date');
const isWhatsappTextNode = computed(() => normalizedNodeType.value.includes('whatsapp') && !normalizedNodeType.value.includes('media'));
const isWhatsappMediaNode = computed(() => normalizedNodeType.value.includes('whatsapp') && normalizedNodeType.value.includes('media'));
const isConditionNode = computed(() => normalizedNodeType.value === 'condition');
const isStopNode = computed(() => ['stop', 'stop_funnel'].includes(normalizedNodeType.value));

const WAIT_UNITS = [
  { value: 'seconds', label: 'Segundos' },
  { value: 'minute', label: 'Minutos' },
  { value: 'hours', label: 'Horas' },
  { value: 'day', label: 'Dias' },
  { value: 'week', label: 'Semanas' },
  { value: 'month', label: 'Meses' },
  { value: 'year', label: 'Anos' },
];
const delayUnitField = computed(() => ({
  placeholder: 'Selecione o período',
  searchable: false,
  options: WAIT_UNITS,
}));
const closeButtonStyle = {
  backgroundImage:
    'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 1 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 1 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z\'/%3e%3c/svg%3e")',
  backgroundPosition: 'center',
  backgroundRepeat: 'no-repeat',
  backgroundSize: '0.75rem auto',
};

const MEDIA_TYPES = [
  { value: 'image', label: 'Imagem' },
  { value: 'video', label: 'Vídeo' },
  { value: 'document', label: 'Documento' },
  { value: 'audio', label: 'Áudio' },
];
const mediaTypeField = computed(() => ({
  placeholder: 'Selecione o tipo de midia',
  searchable: false,
  options: MEDIA_TYPES,
}));

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

const HOURS = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
const MINUTES = Array.from({ length: 60 }, (_, index) => String(index).padStart(2, '0'));

function openWpMediaLibrary() {
  const wpMedia = (window as Window & {
    wp?: {
      media?: (...args: unknown[]) => {
        on: (event: string, callback: () => void) => void;
        state: () => { get: (key: string) => { first: () => { toJSON: () => { url?: string } } | undefined } };
        open: () => void;
      };
    };
  }).wp?.media;

  if (!wpMedia) {
    return;
  }

  const frame = wpMedia({
    title: 'Selecionar midia',
    button: { text: 'Usar esta midia' },
    multiple: false,
  });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first();
    const url = String(attachment?.toJSON?.().url || '').trim();

    if (url) {
      updateConfig('mediaUrl', url);
    }
  });

  frame.open();
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto px-4 py-6"
    >
      <button
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        type="button"
        aria-label="Fechar"
        @click="$emit('close')"
      />

      <div class="relative z-10 max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-white/20 bg-white shadow-2xl">
        <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white px-6 py-5">
          <div>
            <div class="mb-1 flex items-center gap-2">
              <div
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md"
                :class="nodeConfig?.color ?? 'bg-slate-400'"
              >
                <i :class="`bx ${nodeConfig?.icon ?? 'bx-cog'} text-white`" style="font-size: 12px;" />
              </div>
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                Configurações
              </p>
            </div>
            <h3 class="flex items-center gap-2 text-xl font-semibold text-slate-900">
              <span
                v-if="isWhatsappMediaNode"
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md"
                :class="nodeConfig?.color ?? 'bg-slate-400'"
              >
                <i :class="`bx ${nodeConfig?.icon ?? 'bx-cog'} text-white`" style="font-size: 12px;" />
              </span>
              <span>{{ label }}</span>
            </h3>
          </div>
          <button
            type="button"
            class="btn-close box-content flex h-4 w-4 shrink-0 items-center justify-center rounded border-0 bg-transparent p-[0.25rem] opacity-50 transition hover:opacity-75 focus:outline-none focus:opacity-100 disabled:pointer-events-none disabled:select-none disabled:opacity-25"
            :style="closeButtonStyle"
            aria-label="Fechar"
            @click="$emit('close')"
          >
            <span class="sr-only">Fechar</span>
          </button>
        </div>

        <div class="space-y-6 px-6 py-5">
          <div class="space-y-4">
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
              <textarea
                v-model="draftDescription"
                rows="3"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
              />
            </div>
          </div>

          <div class="border-t border-slate-100 pt-5">
            <template v-if="isSnippetNode">
              <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Código PHP</label>
                <div class="overflow-hidden rounded-lg border border-slate-200">
                  <textarea
                    :value="String(draftConfig.snippet_php ?? draftConfig.code ?? '<?php\n\n// Seu código aqui\n')"
                    rows="12"
                    spellcheck="false"
                    class="w-full resize-none bg-slate-900 px-4 py-3 text-sm font-mono text-emerald-400 outline-none"
                    @input="updateConfig('snippet_php', ($event.target as HTMLTextAreaElement).value)"
                  />
                </div>
                <p class="text-xs text-slate-400">
                  Insira o código PHP que será executado nesta etapa.
                </p>
              </div>
            </template>

            <template v-else-if="isTimeDelayNode">
              <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Tempo de espera</label>
                <div class="flex gap-3">
                  <input
                    type="number"
                    min="1"
                    placeholder="Ex: 30"
                    :value="draftConfig.delay_value ?? draftConfig.duration ?? ''"
                    class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('delay_value', ($event.target as HTMLInputElement).value)"
                  />
                  <SelectField
                    name="delay_period"
                    :field="delayUnitField"
                    :model-value="String(draftConfig.delay_period ?? draftConfig.unit ?? 'minute')"
                    :button-class="'h-full min-h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10'"
                    :dropdown-class="'mt-1'"
                    :root-class="'w-44'"
                    @update:model-value="updateConfig('delay_period', $event)"
                  />
                </div>
              </div>
            </template>

            <template v-else-if="isWaitDateNode">
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Data</label>
                  <input
                    type="date"
                    :value="String(draftConfig.date ?? draftConfig.date_value ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @change="updateConfig('date_value', ($event.target as HTMLInputElement).value)"
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
                      <option v-for="hour in HOURS" :key="hour" :value="hour">{{ hour }}h</option>
                    </select>
                  </div>
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Minuto</label>
                    <select
                      :value="String(draftConfig.minute ?? '00')"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @change="updateConfig('minute', ($event.target as HTMLSelectElement).value)"
                    >
                      <option v-for="minute in MINUTES" :key="minute" :value="minute">{{ minute }}min</option>
                    </select>
                  </div>
                </div>
              </div>
            </template>

            <template v-else-if="isWhatsappTextNode">
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Remetente</label>
                  <SelectField
                    name="whatsapp_text_sender"
                    :field="senderField"
                    :model-value="String(draftConfig.sender ?? '')"
                    :button-class="'h-full min-h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10'"
                    :dropdown-class="'mt-1'"
                    @update:model-value="updateConfig('sender', $event)"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Destinatário</label>
                  <input
                    type="text"
                    placeholder="Ex: {{contato.telefone}} ou +5511999990000"
                    :value="String(draftConfig.receiver ?? draftConfig.recipient ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('receiver', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Mensagem</label>
                  <BaseRichTextArea
                    :model-value="String(draftConfig.message ?? '')"
                    placeholder="Digite sua mensagem... Use {{placeholders}}"
                    :rows="5"
                    @update:model-value="updateConfig('message', $event)"
                  />
                </div>
              </div>
            </template>

            <template v-else-if="isWhatsappMediaNode">
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Remetente</label>
                  <SelectField
                    name="whatsapp_media_sender"
                    :field="senderField"
                    :model-value="String(draftConfig.sender ?? '')"
                    :button-class="'h-full min-h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10'"
                    :dropdown-class="'mt-1'"
                    @update:model-value="updateConfig('sender', $event)"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Destinatário</label>
                  <input
                    type="text"
                    placeholder="Ex: {{contato.telefone}} ou +5511999990000"
                    :value="String(draftConfig.receiver ?? draftConfig.recipient ?? '')"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                    @input="updateConfig('receiver', ($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">Tipo de mídia</label>
                    <SelectField
                      name="whatsapp_media_type"
                      :field="mediaTypeField"
                      :model-value="String(draftConfig.mediaType ?? 'image')"
                      :button-class="'h-full min-h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10'"
                      :dropdown-class="'mt-1'"
                      @update:model-value="updateConfig('mediaType', $event)"
                    />
                  </div>
                  <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">URL da mídia</label>
                    <div class="flex gap-2">
                      <input
                        type="url"
                        placeholder="https://exemplo.com/arquivo.jpg"
                        :value="String(draftConfig.mediaUrl ?? '')"
                        class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                        @input="updateConfig('mediaUrl', ($event.target as HTMLInputElement).value)"
                      />
                      <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="openWpMediaLibrary"
                      >
                        <i class="bx bx-image-add text-base" />
                        Biblioteca
                      </button>
                    </div>
                  </div>
                </div>
                <div class="space-y-1.5">
                  <label class="block text-sm font-medium text-slate-700">Legenda</label>
                  <BaseRichTextArea
                    :model-value="String(draftConfig.caption ?? '')"
                    placeholder="Legenda da mídia..."
                    :rows="3"
                    @update:model-value="updateConfig('caption', $event)"
                  />
                </div>
              </div>
            </template>

            <template v-else-if="isConditionNode">
              <div class="space-y-4">
                <label class="block text-sm font-medium text-slate-700">Condições</label>

                <div
                  v-for="(condition, index) in conditions"
                  :key="index"
                  class="flex items-end gap-2"
                >
                  <div class="flex-1 space-y-1">
                    <span v-if="index === 0" class="text-xs text-slate-400">Campo</span>
                    <input
                      type="text"
                      placeholder="Ex: email"
                      :value="condition.field"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @input="updateCondition(index, 'field', ($event.target as HTMLInputElement).value)"
                    />
                  </div>
                  <div class="w-[160px] space-y-1">
                    <span v-if="index === 0" class="text-xs text-slate-400">Operador</span>
                    <select
                      :value="condition.operator"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @change="updateCondition(index, 'operator', ($event.target as HTMLSelectElement).value)"
                    >
                      <option v-for="operator in OPERATORS" :key="operator.value" :value="operator.value">{{ operator.label }}</option>
                    </select>
                  </div>
                  <div class="flex-1 space-y-1">
                    <span v-if="index === 0" class="text-xs text-slate-400">Valor</span>
                    <input
                      type="text"
                      placeholder="Valor"
                      :value="condition.value"
                      class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                      @input="updateCondition(index, 'value', ($event.target as HTMLInputElement).value)"
                    />
                  </div>
                  <button
                    type="button"
                    class="mb-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-red-400 transition-colors hover:bg-red-50 hover:text-red-600"
                    @click="removeCondition(index)"
                  >
                    <i class="bx bx-x" style="font-size: 18px;" />
                  </button>
                </div>

                <button
                  type="button"
                  class="flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:border-primary-400 hover:text-primary-600"
                  @click="addCondition"
                >
                  <i class="bx bx-plus" style="font-size:14px;" />
                  Adicionar condição
                </button>
              </div>
            </template>

            <template v-else-if="isStopNode">
              <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-4">
                <p class="text-sm font-medium text-red-700">
                  Nenhuma ação será executada ao chegar neste ponto.
                </p>
                <p class="mt-1 text-xs text-red-500">
                  O fluxo será interrompido completamente nesta etapa.
                </p>
              </div>
            </template>

            <template v-else>
              <div class="space-y-1.5">
                <label class="block text-sm font-medium text-slate-700">Descrição</label>
                <textarea
                  v-model="draftDescription"
                  rows="4"
                  placeholder="Descreva esta etapa..."
                  class="w-full resize-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/10"
                />
              </div>
            </template>
          </div>
        </div>

        <div class="sticky bottom-0 z-10 flex items-center justify-end gap-3 border-t border-slate-100 bg-white px-6 py-4">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            @click="$emit('close')"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
            @click="handleSave"
          >
            Salvar configurações
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
